# URSSAF Tiers de Prestations

PHP client, domain model and Symfony/Doctrine integration for the URSSAF **"API Tiers de prestations"**, the service
that lets home services providers (services a la personne) offer the **immediate tax-credit advance** to their
customers: the provider registers the customer, sends payment requests, and URSSAF debits the customer for the part
that is not covered by the tax credit and pays the provider.

Package: `thebeardedbear/urssaf-tiers-prestations` - namespace `TheBeardedBear\UrssafTiersPrestations\`.

The full description of the API (fields, rules, codes, statuses, official JSON examples) is in
[docs/api-reference.md](docs/api-reference.md).

## Features

- The four API methods: **010** register a customer, **020** transmission (pairing) status, **050** submit payment
  requests, **070** search payment requests.
- OAuth2 client-credentials authentication with a pluggable access-token cache (PSR-6, PSR-16 or in-memory), renewed
  a configurable number of seconds before expiry.
- Multi-tenant: credentials are resolved per tenant through a `CredentialsProvider`.
- Local validation that mirrors the official rules (identity, addresses, bank account, nature/activity codes, totals,
  VAT, deposit limit, employment period...), reported as a list of violations with stable codes.
- Exact wire encoding of money (cents, thousandths) and dates.
- Registration and pairing workflow (register, submit, wait for pairing, sync pairing).
- Payment request lifecycle: batched submission (at most 10 per call), status polling, reconciliation of stale
  submissions (looked up at URSSAF before anything is sent again, never blindly resubmitted).
- Symfony bundle: configuration, console commands, Messenger messages/handlers, optional auto-submit, translations of
  the violation codes.
- Doctrine ORM persistence (XML mapping shipped with the package, custom DBAL types, atomic claims), with GDPR tools
  (anonymisation, deletion, encryptable columns).
- Optional Symfony Lock per command / handler and tenant against overlapping runs.
- A simulated URSSAF platform (`Testing\SimulatedUrssafGateway`, `gateway.simulated: true`) to test a host application
  end to end without network.

## Requirements

- PHP >= 8.2 (8.2 to 8.5 are tested) with `ext-ctype`, `ext-filter`, `ext-json`, `ext-mbstring`.
- A PSR-18 HTTP client and PSR-17 factories (`psr/http-factory` >= 1.1, for example with `symfony/http-client` and
  `nyholm/psr7` >= 1.8.2).
- Optional: `ext-intl` (better transliteration of customer names; a built-in fallback map is used otherwise),
  `symfony/*` for the bundle, `symfony/lock` to prevent overlapping crons / workers, `doctrine/orm` and `doctrine/dbal`
  for the Doctrine bridge.
- Minimum versions of the optional libraries, the first releases that raise no deprecation on PHP 8.4 and 8.5 (older
  ones are declared as `conflict`): `symfony/{clock,framework-bundle,lock,messenger}` 6.4.3 (6.4 LTS) or 7.4,
  `symfony/dependency-injection` 6.4.26 or 7.4, `doctrine/orm` 3.5.3, `doctrine/dbal` 4.0, `doctrine/doctrine-bundle` 2.19.

## Installation

The package is distributed from a private Git repository. Declare it in your `composer.json`:

```json
{
    "repositories": [
        {"type": "vcs", "url": "git@<your-git-host>:<group>/urssaf-tiers-prestations.git"}
    ]
}
```

then:

```bash
composer require thebeardedbear/urssaf-tiers-prestations
```

## Architecture

Hexagonal / DDD, layers enforced by Deptrac:

```
            +-----------------------------------------------+
            | Bridge (Symfony, Doctrine)  - framework glue  |
            +----------------------+------------------------+
                                   |
            +----------------------v------------------------+
            | Infrastructure - HTTP gateway, OAuth2, wire   |
            | encoding, token stores, clock, ids, in-memory |
            +----------------------+------------------------+
                                   | implements ports
            +----------------------v------------------------+
            | Application - commands, queries, handlers,    |
            | ports (UrssafGateway, repositories...), DTOs  |
            +----------------------+------------------------+
                                   |
            +----------------------v------------------------+
            | Domain - aggregates, value objects, rules     |
            | (pure PHP, no PSR, no framework)              |
            +-----------------------------------------------+
```

- **Domain**: `CustomerRegistration` and `PaymentRequest` aggregates, value objects (`Money`, `CalendarDate`,
  `Instant`...), validation, domain events.
- **Application**: one `final readonly` handler per use case (`RegisterCustomerHandler`,
  `SubmitPaymentRequestsHandler`...), ports implemented by the outer layers.
- **Infrastructure**: `UrssafHttpGateway` (implements `UrssafGateway`, built with `UrssafGatewayFactory`; the OAuth2
  token provider and the JSON client are internal), adapters for clock, ids, events, rate limiting, in-memory
  repositories.
- **Bridge**: `Bridge\Symfony` (bundle, commands, Messenger) and `Bridge\Doctrine` (mapping, types, repositories).

## Quick start A: framework-agnostic

Wire the gateway, the repositories and the handlers by hand. The example uses the in-memory adapters (good for tests
and prototypes); replace the repositories with the Doctrine ones (see [docs/doctrine.md](docs/doctrine.md)) for real use.

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomer;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllCustomerPairingsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairingHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafGatewayFactory;

$tenant = TenantId::fromString('tenant-1');

// Any PSR-18 client / PSR-17 factories, and any PSR-20 clock ($psrClock).
$http = new Psr18ClientOfYourChoice();
$factory = new Psr17Factory();
$clock = new SystemClock($psrClock);

$credentials = new InMemoryCredentialsProvider([
    'tenant-1' => Credentials::of(getenv('URSSAF_CLIENT_ID'), getenv('URSSAF_CLIENT_SECRET')),
]);

// The public entry point to build the gateway: the OAuth2 token provider and the JSON client are internal.
// Use a token store shared by all your processes (PSR-6 / PSR-16 store, dedicated cache pool) in production.
$gateway = UrssafGatewayFactory::create(
    new HttpTransport($http, $factory, $factory),
    $credentials,
    new InMemoryTokenStore(),
    new ApiConfiguration(Environment::Sandbox, null, 'my-application'),
    new GatewayOptions(clock: $clock),   // optional: rate limiter, id generator, clock, PSR-3 logger
);

$registrations = new InMemoryCustomerRegistrationRepository();
$unitOfWork = new UnitOfWork(new ImmediateTransactionManager(), new NullEventPublisher());
$policy = new WorkflowPolicy();

// 1. Record the registration locally (validated, nothing is sent yet). $draft is a Domain\Customer\Input\CustomerDraft.
$registrationId = (new RegisterCustomerHandler(
    $registrations,
    new RandomUuidGenerator(new SecureRandomBytes()),
    $clock,
    $unitOfWork,
))(new RegisterCustomer($tenant, ExternalReference::fromString('customer-42'), $draft));

// 2. Send it to URSSAF (method 010): the registration moves to "awaiting_pairing".
$outcome = (new SubmitCustomerRegistrationHandler($registrations, $gateway, $clock, $unitOfWork))(
    new SubmitCustomerRegistration($registrationId),
);

// 3. Later (cron): poll the pairing status (method 020). Once the customer accepted, the state is "registered".
$sync = new SyncCustomerPairingHandler($registrations, $gateway, $clock, $unitOfWork, $policy);
```

Payment requests follow the same pattern: `CreatePaymentRequestHandler` (local validation and storage), then
`SubmitPaymentRequestsHandler` / `SubmitPendingPaymentRequestsHandler` (method 050, at most 10 per call), then
`SyncPaymentRequestStatusesHandler` / `SyncAllPaymentRequestStatusesHandler` (method 070) and
`ReconcileStaleSubmissionsHandler`. Every handler is a `final readonly` class invoked with its command object and
returns an id or a `BatchReport`. Queries: `GetCustomerRegistrationHandler`, `FindPaymentRequestsHandler`.

## Quick start B: Symfony

Register the bundle (`config/bundles.php`):

```php
return [
    // ...
    TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\UrssafTiersPrestationsBundle::class => ['all' => true],
];
```

Configure it (`config/packages/urssaf_tiers_prestations.yaml`):

```yaml
urssaf_tiers_prestations:
    environment: '%env(URSSAF_ENV)%'   # sandbox | production
    base_uri: ~
    application_id: 'my-application'
    token_safety_margin: 60
    http_client: ~
    http_timeout: 15
    credentials:
        tenant-1:
            client_id: '%env(URSSAF_TENANT1_CLIENT_ID)%'
            client_secret: '%env(URSSAF_TENANT1_CLIENT_SECRET)%'
    tenants: [tenant-1]
    token_store: { cache_pool: urssaf.token_cache }   # a dedicated cache pool, see docs/symfony.md
    rate_limiter: { enabled: true }
    workflow: { business_timezone: Europe/Paris, settled_max_age: P90D, stale_submission_after: PT15M, billing_third_party_id: menage.fr, batch_size: 10, default_page_size: 100, max_page_size: 1000 }
    persistence: { driver: doctrine, entity_manager: urssaf }   # a DEDICATED entity manager, see docs/doctrine.md
    lock: { enabled: auto }                                      # symfony/lock: no overlapping run per command and tenant
    messenger: { enabled: true, bus: messenger.default_bus, auto_submit: true }
```

Every option is described in [docs/symfony.md](docs/symfony.md). Create the tables with
`doctrine:migrations:diff` (the package ships no migration, see [docs/doctrine.md](docs/doctrine.md)), then check the
installation with `php bin/console urssaf:check` (credentials, authentication, token store, schema, lock).

Messenger: the four messages are handled automatically; route them to your transport:

```yaml
framework:
    messenger:
        routing:
            'TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitCustomerRegistrationMessage': async
            'TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitPaymentRequestsMessage': async
            'TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantCustomerPairingMessage': async
            'TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantPaymentStatusesMessage': async
```

Cron (polling and recovery run from the console), or the equivalent Symfony Scheduler schedule
(`scheduler: { enabled: true }`, see [docs/symfony.md](docs/symfony.md#scheduler)):

```cron
# pairing of the customers awaiting it (also blocked / mandate_expired ones)
*/10 * * * *  php bin/console urssaf:customers:sync-pairing
# status of the submitted payment requests
*/15 * * * *  php bin/console urssaf:payment-requests:sync-statuses
# submissions left without answer: looked up at URSSAF, never resent blindly
*/15 * * * *  php bin/console urssaf:customers:reconcile-stale
*/15 * * * *  php bin/console urssaf:payment-requests:reconcile-stale
# send what is pending: the safety net of auto_submit (messages can be lost), the only trigger without it
*/5  * * * *  php bin/console urssaf:customers:submit-pending
*/5  * * * *  php bin/console urssaf:payment-requests:submit-pending
```

## Workflow

```
register -> submit (010) -> awaiting pairing -> sync pairing (020) -> registered
   -> create payment request -> submit (050, <= 10 per call) -> sync statuses (070) -> final status
```

1. The provider records the customer (`RegisterCustomer`): validated locally, state `pending`.
2. `SubmitCustomerRegistration` sends it (method 010): state `awaiting_pairing`. The customer must then accept the
   pairing on the URSSAF portal.
3. `SyncCustomerPairing` polls method 020 until the pairing is valid: state `registered`.
4. `CreatePaymentRequest` is only possible for a registered customer, and validates the invoice against the official
   rules; state `pending`.
5. `SubmitPaymentRequests` sends up to 10 requests per call (method 050): `submitted` or `rejected`.
6. `SyncPaymentRequestStatuses` follows the URSSAF status (method 070) until it is final (or, for a paid request,
   until `settled_max_age` has elapsed).

State machines, idempotency, retry and polling rules: [docs/workflow.md](docs/workflow.md).

## Status tables

The URSSAF payment request statuses (10 integrated ... 270 regularised by the provider), the pairing statuses and the
error code catalogue are listed in [docs/api-reference.md](docs/api-reference.md) (section 3). They are modelled by
`Domain\PaymentRequest\PaymentRequestStatus` (backed by the official code) and `Domain\Customer\PairingStatus`.

## Error handling

All exceptions thrown by the package implement `Domain\Exception\UrssafException` (a `Throwable` marker).

- `Domain\Exception\RetryableException` (extends `UrssafException`): transient failures, the same operation may
  succeed later. Implemented by `UrssafUnavailable` (HTTP 429/500/503 or transport error),
  `RateLimitExceeded` and `RetryableUrssafFailure`.
- `Domain\Exception\DomainException` (abstract): carries a stable violation code (`violationCode()`), scalar
  `params()` and `toViolation($path)`. Subclasses include `InvalidValue`, `InvariantViolation`, `InvalidStateTransition`,
  `RecordNotFound`, `ValidationFailed`, `CustomerAlreadyRegistered`, `DuplicateInvoiceNumber`.
- `ValidationFailed::result()` returns the `ValidationResult` listing every `Violation`: code `<subject>.<reason>` in
  snake_case (for example `birth_date.in_future`), path in English camelCase with indexes
  (`invoice.lines[0].quantity`), params, message.
- Gateway failures: `UrssafRequestRejected` (HTTP 400, business refusal with the URSSAF error codes),
  `UrssafAuthenticationFailed` (401 after retry, 403, 512), `UnexpectedUrssafResponse`.

Violation codes are translated by the Symfony bridge (domain `urssaf_tiers_prestations`, English and French shipped, see
[docs/symfony.md](docs/symfony.md#translations)).

## Security

- Secrets (`client_id`, `client_secret`) come only from a `CredentialsProvider` or from environment variables
  (`%env(...)%`). They are never persisted, `Credentials` is not serializable (`CredentialsNotSerializable`) and
  `client_secret` is marked `#[SensitiveParameter]`.
- The access token lives only in the token store (cache) with a safety margin.
- Logs never contain tokens nor request/response bodies; exception messages never contain secrets nor the rejected raw
  value.

## Testing the package

Everything runs in Docker, no local PHP/Composer is needed:

```bash
make install      # build the image and install dependencies
make test         # unit + functional tests
make coverage     # PHPUnit coverage, 100 % lines/methods/classes enforced
make infection    # mutation testing, 100 % MSI
make ci           # the full quality gate
```

The CI runs the same gates (GitHub Actions, `.github/workflows/ci.yml`: one job per gate, tests on PHP 8.2 / 8.3 / 8.4
with the lowest and highest dependencies, PHP 8.5 allowed to fail). The contract job is manual (Actions > CI > Run workflow,
`contract` checked) and reads the repository secrets `URSSAF_SANDBOX_CLIENT_ID` / `URSSAF_SANDBOX_CLIENT_SECRET` of the
`urssaf-sandbox` environment.

Quality gates (all blocking): PHPUnit with 100 % coverage, Infection with 100 % MSI, PHPStan at level max, Mago
(`make mago`), PHP-CS-Fixer (`make cs`, `make cs-fix`), Rector (`make rector`), Deptrac (`make deptrac`), composer
checks (`make composer-checks`: validate, audit, normalize, require-checker, unused) and `make guard`.
Use `PHP_VERSION=8.3 make ci` to run against another PHP version.

### Contract tests against the sandbox

```bash
export URSSAF_SANDBOX_CLIENT_ID=... URSSAF_SANDBOX_CLIENT_SECRET=...
# optional: URSSAF_SANDBOX_BASE_URI, URSSAF_SANDBOX_CUSTOMER_ID, URSSAF_SANDBOX_INTERVENANT, URSSAF_SANDBOX_CUSTOMER_BIRTH_DATE, URSSAF_SANDBOX_BILLING_ID
make test-contract
```

These tests (`tests/Contract`, group `contract`) are skipped when the credentials are missing.

## Versioning

SemVer. `TheBeardedBear\UrssafTiersPrestations\SpecVersion::CURRENT` tracks the URSSAF technical documentation the
package implements (currently `1.30.19`). See [CHANGELOG.md](CHANGELOG.md).

## More documentation

- [docs/api-reference.md](docs/api-reference.md) - the URSSAF API
- [docs/symfony.md](docs/symfony.md) - bundle configuration, commands, Messenger
- [docs/doctrine.md](docs/doctrine.md) - persistence
- [docs/workflow.md](docs/workflow.md) - state machines and rules
- [docs/testing.md](docs/testing.md) - testing a host application with the simulated URSSAF platform
- [docs/migrating-from-legacy-integration.md](docs/migrating-from-legacy-integration.md) - migrating an in-house integration

## License

Proprietary, all rights reserved. See [LICENSE](LICENSE).
