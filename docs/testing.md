# Testing a host application

The package ships `TheBeardedBear\UrssafTiersPrestations\Testing\SimulatedUrssafGateway`, an in-memory URSSAF platform:
it implements the gateway port (`Application\Port\Urssaf\UrssafGateway`), keeps the registered customers and the submitted
payment requests per tenant, and answers the four methods like the real API, without any network call. Use it to test your
integration (controllers, listeners, crons, Messenger handlers) end to end; keep the sandbox for the contract tests.

It must never be used outside tests: nothing it accepts is sent to URSSAF.

## Default behaviour

| Method | Default answer |
|---|---|
| 010 register | Accepted: a new URSSAF customer id (from the id generator) is returned. |
| 020 pairing status | Each poll returns the next status of the pairing sequence, the last one is repeated. Default sequence: `APPAREILLAGE_VALIDE`, with the pairing acceptance instant = the clock. A customer unknown to the tenant is refused as "no active registration" (`ERR_RECHERCHE_PARTICULIER`). |
| 050 submit | 1 to 10 requests per call. Each one is accepted with a new URSSAF id and the first status of the payment request sequence; an invoice number already submitted by the tenant is rejected as a duplicate (`ERR_FACTURE_DOUBLON`). |
| 070 search | By ids, invoice numbers or invoice period, the tenant's requests only, more than 10 results refused (`ERR_NBRE_MAX_RESULTAT`). Each search returns the next status of every request found, the last one is repeated. Default sequence: `10`, `20`, `30`, `70`. A settled status (`70`, `120`) carries a transfer of the invoice total dated on the clock (business time zone); a refused one (`40`, `60`) carries a rejection. |

## Scripting

```php
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;

$urssaf->refuseNextRegistration('ERR_DGFIP_R2P_INCONNU');                 // 010 refused (HTTP 400)
$urssaf->pairCustomersThrough(PairingStatus::InProgress, PairingStatus::Validated); // customers registered from now on
$urssaf->setPairingStatus($urssafCustomerId, PairingStatus::CustomerBlocked);
$urssaf->rejectNextSubmissionOf('2022-AZ-00001', 'ERR_DATE_FUTUR');       // 050 per-item rejection (kind from the codes)
$urssaf->progressPaymentRequestsThrough(PaymentRequestStatus::Integrated, PaymentRequestStatus::Paid);
$urssaf->setPaymentRequestStatus($urssafPaymentRequestId, PaymentRequestStatus::Refused);
$urssaf->failNext(Operation::SubmitPaymentRequests, UrssafUnavailable::withStatus(Operation::SubmitPaymentRequests, 503, 30));
```

Sequences apply to the customers / payment requests created afterwards; `set*Status()` changes one record at once.
Inspection: `calls()` (every operation received, in order), `customerIds()`, `paymentRequestIds()`.

## Without Symfony

```php
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTiersPrestations\Testing\SimulatedUrssafGateway;

$clock = new FrozenClock(new DateTimeImmutable('2022-03-01T10:00:00Z'));
$urssaf = new SimulatedUrssafGateway($clock, new RandomUuidGenerator(new SecureRandomBytes()));
// Pass $urssaf wherever the handlers expect the UrssafGateway (or one of its four ports).
```

The customer and payment request ids are taken from the id generator: the customer id must be a UUID.

## With the Symfony bundle

Enable the simulator in the test environment only:

```yaml
# config/packages/test/urssaf_tiers_prestations.yaml
urssaf_tiers_prestations:
    gateway: { simulated: true }
```

The gateway port is then the simulator, available as the public service
`TheBeardedBear\UrssafTiersPrestations\Testing\SimulatedUrssafGateway` to script the scenario. The container refuses
`gateway.simulated: true` with `environment: production`.

```php
final class InvoicePaymentTest extends KernelTestCase
{
    public function testTheInvoiceIsMarkedPaidOnceUrssafPaysIt(): void
    {
        self::bootKernel();
        $urssaf = self::getContainer()->get(SimulatedUrssafGateway::class);
        $urssaf->progressPaymentRequestsThrough(PaymentRequestStatus::Integrated, PaymentRequestStatus::Paid);

        // ... register the customer, run urssaf:customers:submit-pending and urssaf:customers:sync-pairing,
        // create the payment request, run urssaf:payment-requests:submit-pending and :sync-statuses ...

        self::assertSame([Operation::RegisterCustomer, Operation::GetCustomerStatus, Operation::SubmitPaymentRequests, Operation::SearchPaymentRequests], $urssaf->calls());
    }
}
```

The simulator lives in the container: it is reset with the kernel (each `bootKernel()`), and shared by the commands and
handlers of the same kernel. Use a clock you control (`framework.test` with `Symfony\Component\Clock\MockClock` as the
`clock` service) to move the pairing acceptance before the employment period of the invoices.
