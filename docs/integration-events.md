# Integration events (outbox)

The host application usually needs to react to the URSSAF workflow: mark an invoice as paid, tell the customer that the
pairing is still pending, show a rejected payment request to an operator. The package publishes these facts as
**integration events**: public, scalar classes (`TheBeardedBear\UrssafTiersPrestations\Contract\Event\*`) versioned
independently of the domain model, written to an **outbox** in the same transaction as the state change, then delivered
by a **relay**.

Guarantees:

- **Durable**: an event exists if and only if its state change was committed (a rolled back use case writes none).
- **At least once**: an event is marked as dispatched only once the sink accepted it; a crash between the delivery and
  the mark delivers it again. Deduplicate on `eventId`: the same fact always has the same id.
- **Ordered per record**: the events of one registration / payment request are delivered in recording order; when one
  fails, the next events of the same record wait for the next run (the other records go on).

The domain events (`Domain\...\Event\*`, dispatched on the event dispatcher after the commit) are still published: they
are an internal mechanism (the `auto_submit` listener uses them) and may change with the domain model. Integrate with the
integration events.

## Catalogue

Common properties: `eventId`, `occurredAt` (ISO 8601, UTC), `tenantId`, `aggregateId` (registration id or local payment
request id), `externalReference` (your reference of the record); `type()` and `version()` (1 for every type today).

| Class | `type()` | When | Specific properties |
|---|---|---|---|
| `CustomerRegistrationSubmitted` | `urssaf.customer_registration.submitted` | URSSAF accepted the registration (010) | `urssafCustomerId` |
| `CustomerPairingChanged` | `urssaf.customer_registration.pairing_changed` | the pairing status changed (020) | `pairingStatus` (`APPAREILLAGE_VALIDE`...), `state` (`registered`, `blocked`...), `registered` |
| `CustomerRegistrationRejected` | `urssaf.customer_registration.rejected` | URSSAF refused the registration (final) | `errorCodes` |
| `PaymentRequestSubmitted` | `urssaf.payment_request.submitted` | URSSAF accepted the payment request (050) | `urssafPaymentRequestId` |
| `PaymentRequestRejected` | `urssaf.payment_request.rejected` | URSSAF rejected the payment request (final) | `errorCodes` |
| `PaymentRequestStatusChanged` | `urssaf.payment_request.status_changed` | a poll observed a new status (070) | `previousStatusCode`, `statusCode`, `final`, `settled` |

Technical failures (retried, or left in `error` / `invalid_credentials` for an operator) have no integration event:
monitor them with `urssaf:status` (`needing_attention`, see [workflow.md](workflow.md#monitoring)). The transfer details
of a settled payment request are read with `FindPaymentRequestsHandler`.

## With the Symfony bundle

```yaml
urssaf_tiers_prestations:
    outbox:
        enabled: true
        sink: messenger              # or event_dispatcher, or the service id of your IntegrationEventSink
        bus: messenger.default_bus
        retention: P30D              # dispatched entries kept for urssaf:outbox:purge

framework:
    messenger:
        routing:
            'TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent': async
```

The outbox is the table `urssaf_tps_outbox` with the `doctrine` driver (generate the migration), in memory with the
`in_memory` driver; with the `custom` driver, give your own `Application\Port\Outbox` as `outbox.service`.

Relay the events, and purge the dispatched ones (or enable the `relay_outbox` / `purge_outbox` tasks of the
[scheduler](symfony.md#scheduler), every minute and every day by default):

```cron
* * * * *  php bin/console urssaf:outbox:relay    # exits with 1 when a delivery failed
0 3 * * *  php bin/console urssaf:outbox:purge
```

The relay takes the lock `urssaf_tps.outbox.relay.*` (when the lock is enabled): runs never overlap.

Handle the events in your application:

```php
#[AsMessageHandler]
final readonly class MarkInvoicePaid
{
    public function __construct(private InvoiceRepository $invoices) {}

    public function __invoke(PaymentRequestStatusChanged $event): void
    {
        if (!$event->settled) {
            return;
        }
        // Idempotent: the same event may be delivered twice.
        $this->invoices->byReference($event->externalReference)->markPaidByUrssaf($event->eventId);
    }
}
```

With the `event_dispatcher` sink, the listeners run in the relay process: a listener that throws keeps the event in the
outbox, delivered again on the next run.

## Without Symfony

Give an outbox to the `UnitOfWork` of the handlers, and run the relay periodically:

```php
$outbox = new DoctrineOutbox($entityManager);   // or InMemoryOutbox, or your Application\Port\Outbox
$unitOfWork = new UnitOfWork(new DoctrineTransactionManager($entityManager), $eventPublisher, $failureReporter, $outbox);

// cron
$report = (new RelayOutboxHandler($outbox, new Psr14IntegrationEventSink($dispatcher), $clock, $policy))(new RelayOutbox(null));
(new PurgeOutboxHandler($outbox, $clock))(new PurgeOutbox(new DateInterval('P30D')));
```

The outbox must use the connection of the package repositories, so that the events join their transaction
(`DoctrineOutbox` uses the connection of the entity manager it is given).
