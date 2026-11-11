# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- CI moved from GitLab CI to GitHub Actions (`.github/workflows/ci.yml`), same gates and PHP matrix.

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
