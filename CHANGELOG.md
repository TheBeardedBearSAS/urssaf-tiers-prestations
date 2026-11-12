# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- French translations of the violation codes (`urssaf_tiers_prestations.fr.yaml`), shipped next to the English ones;
  the test suite checks that every shipped locale has the same keys as the English file.
- `Bridge\Symfony\Validation\ViolationListConverter` (public service when `symfony/validator` is installed): converts a
  `ValidationResult` or a `ValidationFailed` into a Symfony `ConstraintViolationList` (path, code, translated message,
  `%name%` parameters, original violation as cause), for forms and API error responses. See
  [docs/symfony.md](docs/symfony.md#validation-and-forms).

- `Testing\SimulatedUrssafGateway`: an in-memory URSSAF platform implementing the gateway port (registrations,
  pairing statuses, submissions, searches per tenant, scriptable sequences, refusals and failures), and the bundle option
  `gateway.simulated` (refused in production) that wires it as a public service. See [docs/testing.md](docs/testing.md).

- `urssaf:check` console command: credentials, OAuth2 authentication of every tenant (`--skip-remote` to skip it),
  token store, database schema of the package tables, dedicated entity manager and lock; exits with 1 when a check fails.
  Host checks implement `Bridge\Symfony\Diagnostic\InstallationCheck` (autoconfigured).

### Fixed

- The shipped translations were never loaded by the framework: `AbstractBundle` resolves the bundle path one directory
  above the bundle class, so `Resources/translations` was not found. The bundle now declares its own path.

## [0.1.1] - 2026-11-11

### Changed

- CI moved from GitLab CI to GitHub Actions (`.github/workflows/ci.yml`), same gates and PHP matrix. Deprecations are
  blocking in the whole matrix, including the lowest-dependencies runs on PHP 8.4 and 8.5.
- Minimum dependency versions raised to the first releases that are clean on PHP 8.4 and 8.5 (patch levels only, the
  Symfony 6.4 LTS and PHP 8.2 support is kept): `psr/http-factory` ^1.1; `symfony/{clock,framework-bundle,lock,messenger}`
  `<6.4.3`, `symfony/dependency-injection` `<6.4.26`, `nyholm/psr7` `<1.8.2` and `doctrine/orm` `<3.5.3` are now declared
  as `conflict`. Development-only floors (Symfony `^6.4.3 || ^7.4` components and contracts, `doctrine/persistence`,
  `php-http/*`, `symfony/polyfill-php83`) are set in `require-dev` so that `composer update --prefer-lowest` stays
  deprecation-free.

### Fixed

- Symfony 6.4: the bundle no longer passes closures to the service configurator (`factory()` only accepts strings and
  arrays there), so it compiles with every supported Symfony version.
- Symfony Messenger 6.4: `RecoverableMessageHandlingException` has no retry delay argument there; the delay is now
  passed only when the installed version supports it (`MessageFailures` degrades to the transport retry strategy).
- PHP 8.4 with `symfony/var-exporter` 8: the Symfony bundle prepends `doctrine.orm.enable_native_lazy_objects: true`
  when PHP >= 8.4 and the ORM supports native lazy objects (`DoctrineIntegration::nativeLazyObjectsAvailable()`);
  see `docs/doctrine.md` and `docs/symfony.md`.

## [0.1.0] - 2026-11-11

### Added

- Client for the URSSAF "API Tiers de prestations" (specification 1.30.19, `SpecVersion::CURRENT`): method 010
  (register a customer), 020 (transmission status), 050 (submit payment requests, at most 10 per call) and 070
  (search payment requests), through `UrssafHttpGateway`.
- OAuth2 client-credentials authentication with token caching (PSR-6, PSR-16 and in-memory token stores), configurable
  safety margin, single replay on HTTP 401.
- Multi-tenant credentials through `CredentialsProvider`; secrets never persisted, logged or serialized.
- Per-tenant sliding-window rate limiter matching the official quotas.
- Domain model: `CustomerRegistration` and `PaymentRequest` aggregates, value objects, explicit state machines,
  domain events, validation mirroring the official rules with stable violation codes.
- Exact wire encoding of money and dates, error payload parsing and mapping of the URSSAF error codes.
- Application layer: commands and queries with `final readonly` handlers for registration, pairing synchronisation,
  payment request creation, batched submission, status polling, stale submission reconciliation and requeue after
  invalid credentials; `WorkflowPolicy`.
- In-memory repositories for tests and prototypes.
- Doctrine ORM bridge: XML mapping, custom DBAL types, repositories with atomic claims, `urssaf_tps_*` tables
  including the query-side progress table, `DoctrineIntegration` for use without Symfony.
- Symfony bundle (6.4 and 7.x): configuration tree, console commands (`urssaf:customers:submit-pending`,
  `urssaf:customers:sync-pairing`, `urssaf:payment-requests:submit-pending`,
  `urssaf:payment-requests:sync-statuses`, `urssaf:payment-requests:reconcile-stale`,
  `urssaf:requeue-invalid-credentials`), Messenger messages and handlers with failure mapping, optional auto-submit,
  English translations of the violation codes.
- Optional Symfony Lock (`symfony/lock`): every console command and Messenger handler takes a non-blocking lock
  `urssaf_tps.<operation>.<tenant>`; an overlapping command prints "Already running" and exits 0, a handler asks for a
  retry (`RecoverableMessageHandlingException`, 30 s). Configuration: `lock: { enabled: auto|true|false, factory, ttl }`.
- Console command `urssaf:customers:reconcile-stale` (`--dry-run`, `--limit`): reconciles the registrations left
  `submitting`.
- Documentation: GDPR (retention, anonymisation, deletion, column encryption), dedicated entity manager, dedicated token
  cache pool, outbox pattern and safety crons, locks. README
, API reference, Symfony, Doctrine, workflow and migration guides.
- Packaging: `.gitattributes` leaves the development files out of the distribution archive (not `docs/`); `composer.json`
  declares `conflict`s with `doctrine/orm < 3.0`, `doctrine/dbal < 4.0`, `doctrine/doctrine-bundle < 2.19` and
  `symfony/{console,framework-bundle,http-client,http-kernel,lock,messenger} < 6.4`.
- Quality gates: PHPUnit (100 % coverage), Infection (100 % MSI), PHPStan max, Mago, PHP-CS-Fixer, Rector, Deptrac,
  composer checks, live sandbox contract tests (`make test-contract`). The GitLab pipeline has one job per gate, a
PHP 8.2 / 8.3 / 8.4 x lowest / highest test matrix (8.5 allowed to fail) and runs Infection like `make infection`.

### Changed

- `DoctrineTransactionManager` no longer clears the whole entity manager when an operation fails: it detaches only the
  package entities (`CustomerRegistration`, `PaymentRequest`, `PaymentRequestProgress`), host entities stay managed.
  Use a dedicated entity manager (`persistence.entity_manager`): the repositories flush the unit of work they use.
