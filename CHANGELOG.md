# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-11-15

### Added

- Signed releases (workflow `release.yml`): each GitHub release carries the distribution archive, its `SHA256SUMS`
  and a SLSA build provenance attestation (`*.intoto.jsonl`, Sigstore); see *Verifying a release* in SECURITY.md.
- CodeQL analysis of the GitHub Actions workflows (workflow `codeql.yml`) on every pull request and push to `main`.
- Benchmarks (`tests/Benchmark`, `make bench`, PHPBench isolated in `tools/phpbench`): customer validation, wire
  encoding (registration, batch of 10 payment requests) and decoding (submission outcome, search); the CI job
  `benchmark` compares each pull request with its base commit and fails above a 20 % regression.
- Published quality proofs: Codecov (coverage), Stryker Dashboard (mutation report of `main`), SonarQube Cloud (job
  `sonarcloud`) and OpenSSF Scorecard (workflow `scorecard.yml`), with their badges in the README.

### Changed

- CI: every GitHub Action is pinned to a commit SHA, kept up to date by Dependabot (`.github/dependabot.yml`, also
  for Composer).
- Dockerfile linted by hadolint (`make hadolint`, part of `make ci`, CI job `hadolint`); the development image runs as
  a non-root user by default.

- README: maintainer (The Bearded Bear), the other URSSAF packages and professional support; composer
  `authors` now points to https://the-bearded-bear.com.

- `composer.json` `homepage` now points to the project page, shown by Packagist:
  https://the-bearded-bear.com/open-source/urssaf-tiers-prestations. The README links to it and to the overview of
  the URSSAF packages.

## [0.2.0] - 2026-11-15

### Upgrading

- New table `urssaf_tps_outbox` (driver `doctrine`): generate and run the migration (`doctrine:migrations:diff`,
  `doctrine:migrations:migrate`), even when the outbox is disabled, since the mapping is always loaded.
  `urssaf:check` reports the missing table.
- The domain events that have an integration event take the external reference after the tenant id (see Changed), and
  `ServiceIds::PSR_CLOCK` / `PsrClockFactory` are gone (`ClockFactory`).

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

- Monitoring: `Application\Query\GetWorkflowOverviewHandler` counts the records by tenant and state (port
  `Application\Port\WorkflowStatistics`, implemented for the in-memory and Doctrine persistences; optional
  `persistence.workflow_statistics` for a custom one), and the `urssaf:status` command prints them (table or JSON).

- Symfony Scheduler: with `scheduler.enabled`, the bundle registers a schedule (`scheduler.name`, default `urssaf`) that
  runs the crons of the package as `RunCommandMessage`s at configurable frequencies (`scheduler.frequencies.*`, ISO 8601
  durations), an alternative to the crontab.

- Integration events and outbox: public scalar events (`Contract\Event\*`: registration submitted / rejected, pairing
  changed, payment request submitted / rejected / status changed) with a stable `eventId`, written by the `UnitOfWork`
  to an `Application\Port\Outbox` inside the transaction (`IgnoredOutbox` by default; `InMemoryOutbox`, `DoctrineOutbox`
  on the new table `urssaf_tps_outbox`), delivered at least once and in order per record by `RelayOutboxHandler` to an
  `IntegrationEventSink` (Messenger, PSR-14 or your own), purged by `PurgeOutboxHandler`. Bundle: `outbox.*` options,
  `urssaf:outbox:relay` / `urssaf:outbox:purge`, scheduler tasks `relay_outbox` / `purge_outbox`. See
  [docs/integration-events.md](docs/integration-events.md).

- `Standalone\UrssafTiersPrestationsBuilder`: wires every use case without a framework (in-memory defaults,
  `withDoctrine()`, token store, clock, logger, rate limiter, events, outbox, policy, gateway) and builds
  `Standalone\UrssafTiersPrestations`, which returns the handlers. See [docs/standalone.md](docs/standalone.md).

- Symfony Flex recipe (`recipe/`): registers the bundle, copies a commented configuration (simulated gateway in the
  `test` environment) and adds the `URSSAF_*` variables to `.env`; `bin/build-flex-endpoint` builds a Flex
  endpoint to try the recipe before publishing it in symfony/recipes-contrib.

### Changed

- The package is now open source under the MIT license (previously proprietary). Added `CONTRIBUTING.md` and
  `SECURITY.md`, and a notice in the README stating that the package is unofficial and not affiliated with URSSAF.

- The domain events of the registration and payment request workflows that have an integration event carry the
  external reference of the record (`externalReference()`); their constructors take it after the tenant id.

- Development floor `symfony/doctrine-bridge` 6.4.11 (the earlier versions raise a deprecation on PHP 8.5).

### Fixed

- `environment: '%env(URSSAF_ENV)%'` (the documented configuration) broke the container: the environment was converted
  to the enum at build time. It is now resolved at runtime, and the configuration accepts the placeholder.
- The bundle required `symfony/clock` without declaring it: without a `clock` service it now falls back to the UTC
  system clock of the package.
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
