# Security review, November 2026

| | |
|---|---|
| Date | 2026-11-16 |
| Reviewer | The project maintainer, assisted by an AI code assistant (Claude) |
| Scope | `thebeardedbear/urssaf-tiers-prestations`, commit `e1e88b71cff974e12a0f7b07e5f64585b92d941d` (`main`, after release v0.2.1): everything under `src/`, the Composer configuration, the GitHub workflows, composite action and Dependabot configuration, and the repository protection settings |
| Reference | [Assurance case](assurance-case.md): security requirements SR1 to SR7, threat model and trust boundaries |
| Next review | At the latest in November 2027, or earlier after a change of a trust boundary |

## Method

1. **Design review against the security requirements and the trust boundaries** of the assurance case: for each
   boundary (host to package, package to URSSAF, package to database, package to cache, package to logs, pipeline to
   consumers) the data crossing it was identified and the control that protects it was located in the code.
2. **Manual code review** of the security-relevant code: credentials and token handling (`Application/Port/Credentials`,
   `AccessToken`, `Infrastructure/Urssaf/Http/OAuth2TokenProvider`, `Infrastructure/TokenStore/*`), the HTTP client and
   URL building (`JsonApiClient`, `ApiConfiguration`, `Environment`, `UrssafGatewayFactory`), the decoding and encoding of
   untrusted payloads (`Infrastructure/Urssaf/Wire/*`), validation of customer and invoice data (`src/Domain/**`),
   logging and exception messages (every `logger->` call and exception factory), randomness (`Infrastructure/Id/*`),
   Doctrine repositories, types and serializers, GDPR operations, the outbox and the integration event codec, the
   Symfony bundle configuration and registrars, and the diagnostics.
3. **Targeted searches** for dangerous constructs in `src/`: `unserialize`, `serialize`, `eval`, `exec`, weak hashes
   and random functions, TLS options, `http://` literals, raw SQL concatenation, `var_dump`/`print_r`, direct reads
   of the credentials.
4. **CI and supply-chain review**: the four workflows, the composite setup action, Dependabot configuration, release
   signing, and the settings of the GitHub repository (branch protection, secret scanning, workflow token defaults),
   read through the GitHub API.
5. **Tool support** (complements, does not replace the human review): `composer audit --locked` run on the lock file
   during this review (no advisory found); the standing gates PHPStan level max, Mago, Deptrac, 100% coverage,
   Infection at 100% MSI, PHP-CS-Fixer and Rector run on every pull request; CodeQL on the workflows, OpenSSF Scorecard
   and SonarQube Cloud (when enabled) as described in the assurance case.

Not done: no dynamic testing (fuzzing, penetration test) and no review of the third-party development tools beyond
`composer audit`. The live URSSAF sandbox was not exercised.

## Result

No vulnerability exploitable in the default or the documented configuration was found. The review recorded eleven
observations, all low or informational, listed below. The main design properties were confirmed: no third-party runtime
code, secrets never logged nor serialised, strict validation at construction, strict typed decoding of responses, exact
money representation, consistent tenant scoping in persistence, pinned and least-privilege CI.

## Findings

Severity scale: Critical, High, Medium, Low, Informational. Status: Open (accepted for a later hardening change),
Accepted (residual risk assumed by design or by the host), Mitigated.

| ID | Severity | Description | Status |
|---|---|---|---|
| SR-01 | Low | The base URI override (`ApiConfiguration`, bundle option `base_uri`) accepts any string: an `http://` URL would send the client secret and token in clear. The default environments are HTTPS and the override is an operator setting, but a guard (refuse non-HTTPS outside a loopback host) is cheap. Redirect handling and TLS verification are those of the PSR-18 client provided by the host; the package neither enables nor disables them. | Open |
| SR-02 | Low | The response body is read in full (`(string) $response->getBody()` in `JsonApiClient` and `OAuth2TokenProvider`) and decoded with a depth limit of 512, without a size cap. A malicious or compromised endpoint could force a large allocation. Mitigated by the client timeout and PHP `memory_limit`; the cap would have to be applied on the stream. | Open |
| SR-03 | Low | The outbox stores integration events with PHP `serialize()` and reads them with `unserialize()` restricted by `allowed_classes` to the six scalar event classes (`IntegrationEventCodec`). Not exploitable through URSSAF input, and an attacker able to write the outbox table already controls the database, but a plain JSON codec would remove the class of risk and the coupling of stored data to class layout. | Open |
| SR-04 | Low | `PsrLoggerFailureReporter` and `RelayOutboxHandler` / `DoctrineOutbox::markFailed()` record `Throwable::getMessage()` of a failure raised by a host listener or sink (log line, `last_error` column). The package's own messages are free of secrets and personal data, but a host exception message is outside its control. Logging the exception class only (as elsewhere in the package) would be consistent. | Open |
| SR-05 | Informational | URSSAF error `message` and `description` are stored verbatim in the database (rejection causes, submission errors) and exposed in views. They are not logged and not part of exception messages, but URSSAF could echo submitted values in them. Covered by the host retention policy and anonymisation. | Accepted |
| SR-06 | Informational | The access token is stored in clear in the PSR-6 / PSR-16 pool for its lifetime (default pool `cache.app`, often a filesystem directory). Short lived and bound to one client id, but the host must keep the pool private. Listed in the assumptions of the assurance case. | Accepted |
| SR-07 | Informational | Personal data (identity, contact details, address, IBAN, BIC) is stored in clear columns by default. Encryption of selected columns through replaceable DBAL types is documented (`docs/doctrine.md`); database encryption at rest, access control and backup retention belong to the host. | Accepted |
| SR-08 | Informational | `client_secret` handling depends on the host: `%env()%` placeholders are documented, but a literal value in committed configuration is accepted by the bundle and would end in the compiled container. A host-side secret store is assumed. | Accepted |
| SR-09 | Informational | The default rate limiter is per process (`SlidingWindowRateLimiter`): several workers can together exceed the URSSAF quotas. A shared limiter can be injected (`GatewayOptions::withRateLimiter()`); this is an availability concern, not a confidentiality or integrity one. | Accepted |
| SR-10 | Low | Repository settings: `main` requires pull requests, 16 status checks, up-to-date branches, linear history, no force-push and applies to administrators, but requires 0 approving reviews (single maintainer), does not require signed commits, and Dependabot security updates are disabled (Dependabot version updates for Composer and GitHub Actions are configured, and `composer audit` runs in CI). The default workflow token is read-only, but workflows are allowed to approve pull requests. | Open |
| SR-11 | Low | Hardening of the CI: the checkouts of the `ci.yml` jobs keep the default `persist-credentials: true` (the token is read-only, but the CodeQL, Scorecard and release checkouts already disable it). The SonarQube Cloud analysis only runs when the `SONARCLOUD` repository variable is enabled, so it is not a guaranteed gate. CodeQL does not analyse PHP: PHP code security relies on PHPStan, Mago and SonarQube Cloud. | Open |

No finding of severity Medium or higher.

## Confirmed controls (positive results)

- `composer.json` requires only PSR interfaces and PHP extensions at runtime; the lock file has no known advisory.
- Credentials: private constructor and validation, masked `__debugInfo()`, `#[SensitiveParameter]`, serialisation
  refused (`CredentialsNotSerializable`); the secret is read only to build the token request.
- Logging: only method, path, status, duration, request id and exception class (and counts) are logged by the package;
  bodies, tokens and personal data are not.
- Exception messages carry codes, statuses and field names, never the rejected value.
- All validation regexes are anchored and use the `D` modifier; no catastrophic-backtracking pattern was identified.
- Responses are decoded with `json_decode` into arrays and read through typed accessors; an unknown status or invalid
  JSON raises `UnexpectedUrssafResponse`.
- Randomness comes from `random_bytes()`; weak generators are absent from `src`.
- Persistence uses named parameters; interpolated identifiers are constants or Doctrine metadata.
- Tenant scoping is applied in repository queries; locks and token cache keys are per tenant or per client.
- The simulated gateway is refused in the production environment at configuration time.
- Workflows: pinned action SHAs, read-only default permissions, scoped elevation, no `pull_request_target`, no
  interpolation of untrusted input in `run` scripts, signed release assets with provenance.

## Residual risks and assumptions on the host

See section 7 of the [assurance case](assurance-case.md#7-assumptions-on-the-host-application): secure storage of the
`client_secret`, a private token cache, TLS and redirect behaviour of the PSR-18 client, database protection and
encryption at rest, backup retention, retention and erasure policy, and absence of body logging in the host.

## Follow-up

The open findings are tracked as hardening work for the next releases. Vulnerabilities are reported privately, see
[SECURITY.md](../../SECURITY.md).
