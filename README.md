# URSSAF Tiers de Prestations

<!-- the-bearded-bear:badges:start -->
[![CI](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations/actions/workflows/ci.yml?query=branch%3Amain) [![Coverage](https://codecov.io/gh/TheBeardedBearSAS/urssaf-tiers-prestations/branch/main/graph/badge.svg)](https://app.codecov.io/gh/TheBeardedBearSAS/urssaf-tiers-prestations) [![Mutation score](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FTheBeardedBearSAS%2Furssaf-tiers-prestations%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/TheBeardedBearSAS/urssaf-tiers-prestations/main) [![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tiers-prestations&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=TheBeardedBearSAS_urssaf-tiers-prestations) [![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tiers-prestations&metric=sqale_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tiers-prestations) [![Reliability](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tiers-prestations&metric=reliability_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tiers-prestations) [![Security](https://sonarcloud.io/api/project_badges/measure?project=TheBeardedBearSAS_urssaf-tiers-prestations&metric=security_rating)](https://sonarcloud.io/summary/overall?id=TheBeardedBearSAS_urssaf-tiers-prestations) [![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/TheBeardedBearSAS/urssaf-tiers-prestations/badge)](https://scorecard.dev/viewer/?uri=github.com/TheBeardedBearSAS/urssaf-tiers-prestations) [![OpenSSF Best Practices](https://www.bestpractices.dev/projects/16745/badge)](https://www.bestpractices.dev/projects/16745)
[![Packagist version](https://img.shields.io/packagist/v/thebeardedbear/urssaf-tiers-prestations)](https://packagist.org/packages/thebeardedbear/urssaf-tiers-prestations) [![PHP version](https://img.shields.io/packagist/dependency-v/thebeardedbear/urssaf-tiers-prestations/php)](https://packagist.org/packages/thebeardedbear/urssaf-tiers-prestations) [![Downloads](https://img.shields.io/packagist/dt/thebeardedbear/urssaf-tiers-prestations)](https://packagist.org/packages/thebeardedbear/urssaf-tiers-prestations/stats) [![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](phpstan.neon.dist) [![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
<!-- the-bearded-bear:badges:end -->

PHP client, domain model and Symfony/Doctrine integration for the URSSAF **"API Tiers de prestations"**, the service
that lets home services providers (services a la personne) offer the **immediate tax-credit advance** to their
customers: the provider registers the customer, sends payment requests, and URSSAF debits the customer for the part
that is not covered by the tax credit and pays the provider.

Package: `thebeardedbear/urssaf-tiers-prestations` - namespace `TheBeardedBear\UrssafTiersPrestations\`.

Developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).

> [!IMPORTANT]
> **Unofficial package.** This project is an independent open-source implementation of a public API. It is not
> developed, endorsed, certified or supported by URSSAF, and it is not affiliated with URSSAF in any way. "URSSAF" is
> used only to name the API this package talks to. Using this package does not replace the URSSAF habilitation
> process; the URSSAF documentation remains the authority on the API, and you remain responsible for the declarations
> your application sends.

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
- Integration events (`Contract\Event\*`) written to an outbox in the transaction of each state change and relayed at
  least once, in order per record, to Messenger, the event dispatcher or a sink of your own.
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

```bash
composer require thebeardedbear/urssaf-tiers-prestations
```

With Symfony Flex, the recipe of the package (published in
[symfony/recipes-contrib](https://github.com/symfony/recipes-contrib)) registers the bundle, copies a commented
`config/packages/urssaf_tiers_prestations.yaml` (the simulated URSSAF platform in the `test` environment) and adds
`URSSAF_ENV`, `URSSAF_CLIENT_ID` and `URSSAF_CLIENT_SECRET` to `.env`. Contrib recipes are applied once the application
allows them (`composer config extra.symfony.allow-contrib true`, asked by Flex at the first contrib package); without
Flex, do these steps by hand (Quick start B below).

Then generate the tables of the package (it ships no migration) and check the installation:

```bash
php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate
php bin/console urssaf:check
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

`Standalone\UrssafTiersPrestationsBuilder` wires the gateway, the persistence and every use case:

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomer;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTiersPrestations\Standalone\UrssafTiersPrestationsBuilder;

$factory = new Psr17Factory();
$urssaf = UrssafTiersPrestationsBuilder::create(
    new HttpTransport($psr18Client, $factory, $factory),   // any PSR-18 client and PSR-17 factories
    new InMemoryCredentialsProvider(['tenant-1' => Credentials::of(getenv('URSSAF_CLIENT_ID'), getenv('URSSAF_CLIENT_SECRET'))]),
    new ApiConfiguration(Environment::Sandbox, null, 'my-application'),
)
    ->withDoctrine($urssafEntityManager)    // default: in-memory repositories (tests, prototypes)
    ->withTokenStore($sharedTokenStore)     // default: in-memory (a token per process)
    ->build();

// 1. Record the registration locally (validated, nothing is sent yet). $draft is a Domain\Customer\Input\CustomerDraft.
$registrationId = $urssaf->registerCustomer()(new RegisterCustomer(TenantId::fromString('tenant-1'), ExternalReference::fromString('customer-42'), $draft));
// 2. Send it to URSSAF (method 010); then poll the pairing (cron) with $urssaf->syncCustomerPairing().
$urssaf->submitCustomerRegistration()(new SubmitCustomerRegistration($registrationId));
```

Payment requests follow the same pattern: `createPaymentRequest()` (local validation and storage), then
`submitPendingPaymentRequests()` (method 050, at most 10 per call), `syncPaymentRequestStatuses()` (method 070) and
`reconcileStaleSubmissions()`; queries: `getCustomerRegistration()`, `findPaymentRequests()`, `getWorkflowOverview()`.
Every option of the builder (clock, logger, rate limiter, events, outbox, policy) and the manual wiring are described in
[docs/standalone.md](docs/standalone.md).

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
- Security design and review: [assurance case](docs/security/assurance-case.md) (threat model, trust boundaries,
  design principles, countered weaknesses) and [security review](docs/security/security-review-2026-11.md).

## Testing the package

Everything runs in Docker, no local PHP/Composer is needed:

```bash
make install      # build the image and install dependencies
make test         # unit + functional tests
make coverage     # PHPUnit coverage, 100 % lines/methods/classes enforced
make coverage-branches # branch coverage (Xdebug, slow), at least 96 % enforced
make infection    # mutation testing, 100 % MSI
make bench        # benchmarks (PHPBench): validation, wire encoding and decoding
make ci           # the full quality gate
```

The CI runs the same gates (GitHub Actions, `.github/workflows/ci.yml`: one job per gate, tests on PHP 8.2 / 8.3 / 8.4
with the lowest and highest dependencies, PHP 8.5 allowed to fail). The contract job is manual (Actions > CI > Run workflow,
`contract` checked) and reads the repository secrets `URSSAF_SANDBOX_CLIENT_ID` / `URSSAF_SANDBOX_CLIENT_SECRET` of the
`urssaf-sandbox` environment.

Quality gates (all blocking): PHPUnit with 100 % coverage and at least 96 % branch coverage (`make coverage-branches`, Xdebug path coverage; measured 96.6 %; slow, so the CI runs it on `main`, nightly and on demand, not on pull requests), Infection with 100 % MSI, PHPStan at level max, Mago
(`make mago`), PHP-CS-Fixer (`make cs`, `make cs-fix`), Rector (`make rector`), Deptrac (`make deptrac`), composer
checks (`make composer-checks`: validate, audit, normalize, require-checker, unused), hadolint on the Dockerfile
(`make hadolint`) and `make guard`.
Use `PHP_VERSION=8.3 make ci` to run against another PHP version.

Published proofs (public repository, `main`): the coverage report on Codecov, the full mutation report on the Stryker
Dashboard, the SonarQube Cloud quality gate and the OpenSSF Scorecard (`.github/workflows/scorecard.yml`), linked from
the badges at the top of this file. The benchmarks (`tests/Benchmark`, PHPBench in `tools/phpbench`, out of the package
dependencies) run on every pull request against the base commit, and the job fails on a regression of more than 20 %.

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
- [docs/integration-events.md](docs/integration-events.md) - reacting to the workflow (outbox, integration events)
- [docs/standalone.md](docs/standalone.md) - using the package without the Symfony bundle
- [docs/testing.md](docs/testing.md) - testing a host application with the simulated URSSAF platform
- [docs/migrating-from-legacy-integration.md](docs/migrating-from-legacy-integration.md) - migrating an in-house integration

<!-- the-bearded-bear:start -->
## Other URSSAF packages

The same architecture, quality gate and conventions, one package per URSSAF API (overview: [the-bearded-bear.com/open-source/urssaf](https://the-bearded-bear.com/open-source/urssaf)):

| API | Package | Status |
|---|---|---|
| Attestations Vérification Code Sécurité (certificate verification) | [`thebeardedbear/urssaf-attestations-verification-code-securite`](https://github.com/TheBeardedBearSAS/urssaf-attestations-verification-code-securite) | Released (Packagist) |
| DPAE (pre-employment declaration) | [`thebeardedbear/urssaf-dpae`](https://github.com/TheBeardedBearSAS/urssaf-dpae) | Released (Packagist) |
| Mobilité internationale (posting of workers abroad) | [`thebeardedbear/urssaf-mobilite-internationale`](https://github.com/TheBeardedBearSAS/urssaf-mobilite-internationale) | Released (Packagist) |
| Plateforme numérique (withholding of contributions by digital platforms) | [`thebeardedbear/urssaf-plateforme-numerique`](https://github.com/TheBeardedBearSAS/urssaf-plateforme-numerique) | Released (Packagist) |
| Tierce Déclaration auto-entrepreneur | [`thebeardedbear/urssaf-tierce-declaration-auto-entrepreneur`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-auto-entrepreneur) | Released (Packagist) |
| Tierce Déclaration CESU | [`thebeardedbear/urssaf-tierce-declaration-cesu`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-cesu) | Released (Packagist) |
| Tierce Déclaration PAJE | [`thebeardedbear/urssaf-tierce-declaration-paje`](https://github.com/TheBeardedBearSAS/urssaf-tierce-declaration-paje) | Released (Packagist) |

## Professional support

This package is developed and maintained by [The Bearded Bear](https://the-bearded-bear.com).
If you need help with an URSSAF integration, The Bearded Bear can help with:

- the URSSAF habilitation and homologation process (subscription, sandbox tests, move to production);
- integrating the package into your application (Symfony, Doctrine or framework-free);
- support with a guaranteed response time, including the follow-up of URSSAF API changes;
- custom developments on top of the package.

Project page and integration offer: [the-bearded-bear.com/open-source/urssaf-tiers-prestations](https://the-bearded-bear.com/open-source/urssaf-tiers-prestations).
<!-- the-bearded-bear:end -->

## Contributing and security

Contributions are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md) and the [Code of Conduct](CODE_OF_CONDUCT.md).
The project's governance is described in [GOVERNANCE.md](GOVERNANCE.md) and its direction in [ROADMAP.md](ROADMAP.md).
Report vulnerabilities privately as described in [SECURITY.md](SECURITY.md), never in a public issue.

## License

MIT. See [LICENSE](LICENSE). The software is provided "as is", without warranty of any kind.

The API specification summarised in [docs/api-reference.md](docs/api-reference.md) belongs to URSSAF; see the sources
listed there.
