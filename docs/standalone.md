# Without the Symfony bundle

`TheBeardedBear\UrssafTiersPrestations\Standalone\UrssafTiersPrestationsBuilder` builds every use case of the package
from a PSR-18 transport, a `CredentialsProvider` and the `ApiConfiguration`; `build()` returns
`Standalone\UrssafTiersPrestations`, whose methods return the handlers (`registerCustomer()`,
`submitCustomerRegistration()`, `syncCustomerPairing()`, `syncAllCustomerPairings()`, `reconcileStaleRegistrations()`,
`createPaymentRequest()`, `submitPaymentRequests()`, `submitPendingPaymentRequests()`, `syncPaymentRequestStatuses()`,
`syncAllPaymentRequestStatuses()`, `reconcileStaleSubmissions()`, `requeueAfterInvalidCredentials()`,
`getCustomerRegistration()`, `findPaymentRequests()`, `getWorkflowOverview()`, `relayOutbox()`, `purgeOutbox()`) and the
gateway (`gateway()`). The same use cases as the Symfony bundle, wired the same way.

Every `with...()` method returns a new builder:

| Method | Default | Use |
|---|---|---|
| `withDoctrine(EntityManagerInterface)` | in-memory repositories | Doctrine repositories, transactions and statistics on a **dedicated** entity manager ([doctrine.md](doctrine.md)). |
| `withRepositories($registrations, $paymentRequests, $transactions, ?$statistics)` | in-memory | Your own persistence; without statistics `getWorkflowOverview()` throws. |
| `withTokenStore(TokenStore)` | in-memory | A store shared by every process (`Psr6TokenStore`, `Psr16TokenStore`). |
| `withClock(Clock)` | UTC system clock | For instance `new SystemClock($psr20Clock)`. |
| `withIdGenerator(IdGenerator)` | random UUIDs | |
| `withEventPublisher(EventPublisher)` | none | Domain events after each commit, e.g. `new Psr14EventPublisher($dispatcher)`. |
| `withLogger(LoggerInterface)` | none | Logs of the HTTP gateway and of the event publication failures. |
| `withRateLimiter(RateLimiter)` | none | `SlidingWindowRateLimiter` to enforce the URSSAF quotas in the process. |
| `withPolicy(WorkflowPolicy)` | defaults | Batch size, page sizes, polling windows, business time zone... |
| `withOutbox(Outbox, IntegrationEventSink)` | no outbox | Integration events (see [integration-events.md](integration-events.md)); `relayOutbox()` needs it. |
| `withGateway(UrssafGateway)` | HTTP gateway | For instance the `Testing\SimulatedUrssafGateway` in tests ([testing.md](testing.md)). |

Schedule the polling and recovery use cases yourself (cron), like the console commands of the bundle:
`syncAllCustomerPairings()`, `syncAllPaymentRequestStatuses()`, `reconcileStaleRegistrations()`,
`reconcileStaleSubmissions()`, `submitPendingPaymentRequests()` per tenant, and `relayOutbox()` with the outbox. Two runs
of the same use case must not overlap (use a lock of your own); the atomic claims keep them safe but wasteful.

## Manual wiring

The builder only calls public constructors: wire the classes yourself when you need another combination.

```php
$gateway = UrssafGatewayFactory::createWith($transport, $credentials, $tokenStore, $configuration, GatewayOptions::defaults()->withClock($clock));
$unitOfWork = new UnitOfWork(new DoctrineTransactionManager($entityManager), $eventPublisher, new PsrLoggerFailureReporter($logger), $outbox);
$registrations = new DoctrineCustomerRegistrationRepository($entityManager);

$registerCustomer = new RegisterCustomerHandler($registrations, $ids, $clock, $unitOfWork);
$submitCustomerRegistration = new SubmitCustomerRegistrationHandler($registrations, $gateway, $clock, $unitOfWork, $policy);
```
