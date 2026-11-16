# Security assurance case

This document argues why the security requirements of `thebeardedbear/urssaf-tiers-prestations` are met. It is
grounded in a review of the code at the commit named in the [security review](security-review-2026-11.md): each claim
points to the file that implements it, and the document states its limits. The package is a library, so part of the
security of a deployment belongs to the host application (see [Assumptions on the host](#7-assumptions-on-the-host-application)).

Reporting a vulnerability: [SECURITY.md](../../SECURITY.md).

## 1. What is protected

The package registers private individuals with the URSSAF "API Tiers de prestation" and sends payment requests
(immediate tax credit advance), authenticating with OAuth2 client credentials.

| Asset | Where it lives | Sensitivity |
|---|---|---|
| OAuth2 `client_id` / `client_secret` of each tenant | Host configuration or `CredentialsProvider`; in memory in `Application\Port\Credentials` | Secret |
| Access token | `TokenStore` (PSR-6 / PSR-16 cache pool), in memory during a call | Secret, short lived |
| Personal data of customers: identity, birth place, contact details, address, IBAN / BIC, account holder | `Domain\Customer\*`, host database (Doctrine mapping), sent to URSSAF | Personal data (GDPR), financial |
| Declarations and amounts of payment requests (invoice, service lines, totals) | `Domain\PaymentRequest\*`, host database, sent to URSSAF | Integrity critical (tax credit) |
| Integration events (outbox) | `urssaf_tps_outbox` table or in memory | Technical identifiers only, no personal data |
| Source code and releases | GitHub repository, Packagist, release assets | Integrity critical (supply chain) |

## 2. Security requirements

| ID | Requirement |
|---|---|
| SR1 | Credentials and tokens are never persisted by the package, never logged, never present in exception messages, and masked when dumped. |
| SR2 | Personal data is never logged by the package, never present in exception messages, and is erasable (GDPR). |
| SR3 | The declarations and amounts sent to URSSAF are what the host asked for: validated, exactly represented (no float drift), and not duplicated by retries. |
| SR4 | Everything received from URSSAF is untrusted: parsed strictly, never executed or deserialised into arbitrary objects, and a malformed response fails closed. |
| SR5 | Requests are authenticated and sent to the intended URSSAF host; one tenant can neither use nor see another tenant's credentials, tokens or records. |
| SR6 | A failure of URSSAF or of the network degrades gracefully: bounded retries, an explicit "may have reached URSSAF" signal, no data loss, no unbounded loop. |
| SR7 | The code and the release artefacts come from the maintainers' reviewed and tested commits and can be verified by consumers. |

## 3. Threat model

| Actor | Capability considered | Main threats |
|---|---|---|
| Network attacker | Observes or tampers with traffic between the host and URSSAF | Eavesdropping on tokens or personal data, response tampering, downgrade |
| Malicious or compromised URSSAF endpoint (or a proxy configured as such) | Returns arbitrary bodies, statuses and sizes | Injection through response fields, deserialisation, resource exhaustion, secrets in error text |
| Host application bug or misconfiguration | Controls configuration, logger, cache, database, listeners | Secrets in clear configuration, `http://` base URI, shared cache, verbose logs, no retention policy |
| Other tenant of a multi-tenant host | Uses the same host application | Cross-tenant access to records, tokens or credentials |
| Hostile user-supplied data | Names, addresses, IBAN, invoice lines reach the package through the host | Malformed values reaching URSSAF or the database |
| Malicious contributor, poisoned dependency, abused CI token | Pull request, dependency update, workflow permissions | Backdoor in the package, secret exfiltration from CI, tampered release |

Out of scope: compromise of the host server or the database server itself, and the security of the URSSAF platform
(see [SECURITY.md](../../SECURITY.md#scope)).

## 4. Trust boundaries

```mermaid
flowchart LR
    subgraph Host["Host application (trusted: owns configuration, secrets, logger, cache, database)"]
        APP["Application code"]
        LOG["Logger and log storage"]
        CACHE["Cache pool (token store)"]
        DB[("Database")]
    end
    subgraph Pkg["Package"]
        DOM["Domain: validation"]
        APPL["Application: use cases"]
        INFRA["Infrastructure: HTTP, OAuth2, wire"]
        BR["Bridges: Symfony, Doctrine"]
    end
    URSSAF["URSSAF API (untrusted input)"]
    CI["GitHub CI and release pipeline"]

    APP -- "B1: commands, raw values, credentials" --> APPL
    APPL --> DOM
    APPL --> INFRA
    INFRA -- "B2: HTTPS, TLS by the PSR-18 client" --> URSSAF
    INFRA -- "B4: access token" --> CACHE
    BR -- "B3: personal data" --> DB
    INFRA -- "B5: metadata only" --> LOG
    CI -- "B6: signed release assets" --> APP
```

| Boundary | What crosses | Control in the package |
|---|---|---|
| B1 host to package | Commands and raw values | Value objects validate at construction (`src/Domain/**`: anchored regexes with `/D`, checksums, length and range limits); credentials only through `CredentialsProvider` |
| B2 package to URSSAF | HTTPS requests with a bearer token; untrusted responses | TLS and certificate verification are delegated to the PSR-18 client; strict JSON decoding and typed mapping (`Infrastructure/Urssaf/Wire`) |
| B3 package to database | Personal data at rest | Doctrine named parameters, custom column types that the host can replace to encrypt, GDPR anonymise and delete |
| B4 package to cache | Access token | Hashed cache key, item expires with the token, a malformed item is a cache miss |
| B5 package to logs | Technical metadata | Fixed set of fields: method, path, status, duration, request id, exception class |
| B6 pipeline to consumers | Releases | Pinned actions, least-privilege tokens, SLSA provenance, checksums |

Inside the package, Deptrac enforces the layers that separate these zones (`deptrac.yaml`).

## 5. Secure design principles

Saltzer and Schroeder principles mapped to the code.

| Principle | How it is applied | Where |
|---|---|---|
| Economy of mechanism | No third-party runtime code: `require` lists only PSR interfaces and PHP extensions. The HTTP client, cache and logger come from the host. The JSON encoder is a small own class that refuses floats. | `composer.json`, `Infrastructure/Urssaf/Wire/JsonEncoder.php` |
| Fail-safe defaults | `environment` is mandatory (no implicit production). The simulated gateway cannot be combined with `environment: production`. The default logger is a `NullLogger`. A malformed cached token is a cache miss. Unknown environments are rejected. | `Bridge/Symfony/DependencyInjection/BundleConfiguration.php`, `ConfigurationRules.php`, `Infrastructure/TokenStore/CachedAccessToken.php`, `Infrastructure/Urssaf/GatewayOptions.php` |
| Complete mediation | Every URSSAF call goes through `JsonApiClient::exchange()` (rate limiter, bearer token, request id, status mapping). Value objects have private constructors, so every instance went through validation. | `Infrastructure/Urssaf/Http/JsonApiClient.php`, `src/Domain/**` |
| Open design | Open source, documented protocol mapping, public quality thresholds. Nothing relies on secrecy of the code. | `README.md`, `docs/`, `CONTRIBUTING.md` |
| Separation of privilege | Strict layers: Domain depends on nothing, Application on Domain and Contract, Infrastructure and bridges on the layers below. Integration events are separate scalar classes. | `deptrac.yaml`, `src/Contract/Event` |
| Least privilege | Credentials fetched per tenant on demand; a single OAuth2 scope; the installation check uses its own in-memory token store so it never touches the application tokens; CI workflows default to `contents: read` and elevate per job. | `Application/Port/CredentialsProvider.php`, `Infrastructure/Urssaf/UrssafEndpoints.php`, `Bridge/Symfony/DependencyInjection/GatewayRegistrar.php`, `.github/workflows/*.yml` |
| Least common mechanism | Token cache key is `sha256(environment, base URI, client id)`; locks per operation and tenant; repositories filter on tenant id. | `Infrastructure/Urssaf/Http/OAuth2TokenProvider.php`, `Bridge/Symfony/Lock/OperationLocks.php`, `Bridge/Doctrine/Repository/*` |
| Psychological acceptability | The secure path is the default path: secrets through `%env()%`, one factory to build the gateway, a `urssaf:check` installation diagnostic, a GDPR guide with an encryption example. | `docs/symfony.md`, `docs/doctrine.md`, `Bridge/Symfony/Diagnostic/*` |
| Defence in depth | Secrets are masked in `__debugInfo()`, marked `#[SensitiveParameter]`, not serialisable, not logged, absent from exception messages. Personal data value objects are masked in dumps and have no `__toString()`. | `Application/Port/Credentials.php`, `AccessToken.php`, `Domain/Customer/**` |
| Fail securely | HTTP errors become typed exceptions carrying the status and URSSAF error codes. An unexpected status or body raises `UnexpectedUrssafResponse` instead of being interpreted. Exactly one replay after a 401 and no other automatic retry in the client. | `Infrastructure/Urssaf/Http/JsonApiClient.php`, `Infrastructure/Urssaf/Exception/UnexpectedUrssafResponse.php` |
| Integrity by construction | Amounts are integer cents (`Money`), encoded as exact decimal literals (`JsonDecimal`), floats are refused by the encoder; invoice number duplicates are detected before sending; the exception for a request that may have reached URSSAF is explicit, so a host can reconcile instead of blindly resending. | `Domain/Shared/Money.php`, `Infrastructure/Urssaf/Wire/MoneyEncoding.php`, `Application/Exception/DuplicateInvoiceNumber.php`, `Application/Port/Urssaf/UrssafUnavailable.php` |

## 6. Common implementation weaknesses and how they are countered

| Weakness (OWASP Top 10 2021 / CWE) | Countermeasure | Where, verified by |
|---|---|---|
| SQL injection (A03, CWE-89) | DQL and DBAL with named parameters. The only interpolated identifiers are the outbox table name read from Doctrine metadata and constant entity class names, never request data. | `Bridge/Doctrine/Repository/*`; PHPStan Doctrine extension, review |
| HTTP, header and form injection (CWE-93, CWE-113) | Query and token body built with `http_build_query(..., PHP_QUERY_RFC3986)`; paths are constants; header values come from configuration or generated ids. | `JsonApiClient::buildRequest()`, `OAuth2TokenProvider::fetch()`, `UrssafEndpoints.php` |
| JSON injection (CWE-116) | Every key and string goes through `json_encode` with `JSON_THROW_ON_ERROR`; only null, bool, int, string, arrays, `JsonDecimal` and `JsonObject` are accepted. | `Wire/JsonEncoder.php` |
| Sensitive data exposure (A02, CWE-200, CWE-532) | Logs hold method, path, status, duration, request id and exception class; bodies and tokens are never logged. Exception messages hold codes and statuses, not raw values. Debug output is masked. | `JsonApiClient`, `OAuth2TokenProvider`, `Application/Port/Credentials.php`, `Domain/Exception/InvalidValue.php`; unit tests |
| Cleartext transmission, improper certificate validation (CWE-319, CWE-295) | Default base URIs are `https://`. The package never disables verification (no `verify_peer`, `CURLOPT_*` or stream-context option in `src`). The default Symfony client has a timeout (`http_timeout`, 15 s). The scheme of an overridden base URI is not enforced (finding SR-01). | `Infrastructure/Urssaf/Environment.php`, `GatewayRegistrar.php` |
| Insecure deserialisation (A08, CWE-502) | Responses are decoded with `json_decode` into arrays, never `unserialize`. A cached token must have the exact array shape and types. The outbox uses `unserialize` with an `allowed_classes` list of the six scalar event classes, on data the package wrote itself (finding SR-03). `Credentials` refuses serialisation. | `Wire/JsonDecoder.php`, `TokenStore/CachedAccessToken.php`, `Contract/Event/IntegrationEventCodec.php` |
| Improper input validation (CWE-20) | Anchored regexes with the `D` modifier (no trailing-newline match), character allow-lists, bounded lengths, IBAN mod-97, BIC, postal and municipality codes, dates, phone, e-mail; strict decimal parser; response fields read through typed accessors. | `src/Domain/**`, `Wire/Payload.php`, `Wire/MoneyEncoding.php` |
| Numeric errors (CWE-190, CWE-681) | Integer cents with a digit limit; no float on the encoding path; decoding rounds from the shortest decimal representation; negative or non-finite amounts rejected. | `Domain/Shared/Money.php`, `Decimal.php`, `MoneyEncoding::decodeMoney()` |
| Hard-coded credentials (CWE-798) | None in the repository; secrets come from the host; fixtures use fictitious values; GitHub secret scanning with push protection is enabled. | `CONTRIBUTING.md`, repository settings |
| Weak randomness (CWE-330, CWE-338) | `random_bytes()` through `SecureRandomBytes`, RFC 4122 v4 layout. No `rand`, `mt_rand` or `uniqid` in `src`. Integration event ids are deterministic on purpose (hash of the fact, for deduplication) and are not security tokens. | `Infrastructure/Id/*`, `Application/Service/IntegrationEventFacts.php` |
| Authentication failures (A07) | Client-credentials flow; the token is renewed `token_safety_margin` seconds early (default 60); a 401 invalidates the cached token and triggers one replay; 400, 401, 403 on the token endpoint raise `UrssafAuthenticationFailed` and are not retried. | `OAuth2TokenProvider`, `JsonApiClient::exchange()` |
| Broken access control, tenant isolation (A01, CWE-639) | Credentials resolved per tenant; repository queries scoped by `tenantId`; locks per operation and tenant. | `Bridge/Doctrine/Repository/*`, `Application/Port/CredentialsProvider.php` |
| Resource exhaustion (CWE-400, CWE-770) | Client timeout, in-process sliding-window rate limiter, batch size cap (`MAX_REQUESTS_PER_CALL`), JSON depth limit, one replay at most, non-blocking locks. The response body size is not capped by the package (finding SR-02). | `UrssafHttpGateway`, `SlidingWindowRateLimiter`, `JsonDecoder` |
| Vulnerable and outdated components (A06) | No third-party runtime code. Development tooling: `composer audit` in CI and `make composer-checks`, `roave/security-advisories`, Dependabot weekly (Composer and GitHub Actions), committed `composer.lock`. | `composer.json`, `.github/dependabot.yml`, `.github/workflows/ci.yml` |
| Supply chain, integrity failures (A08) | Actions pinned to a commit SHA; `contents: read` by default, elevated per job; `persist-credentials: false` on the CodeQL, Scorecard and release checkouts; no `pull_request_target`; workflow inputs passed through `env`, not interpolated in scripts; CodeQL for workflows; OpenSSF Scorecard; releases carry `SHA256SUMS` and a SLSA provenance attestation; `main` requires pull requests, status checks and linear history, forbids force pushes and applies to admins. | `.github/workflows/*.yml`, `.github/actions/setup/action.yml`, `SECURITY.md` |
| Logging and monitoring failures (A09) | Metadata-only logs on a dedicated `urssaf` channel; a failure reporter for post-commit failures; `urssaf:status` monitoring; `urssaf:check` diagnostics. | `GatewayRegistrar.php`, `Infrastructure/Logging/PsrLoggerFailureReporter.php`, `docs/workflow.md` |
| SSRF (A10, CWE-918) | The path is a constant; only the base URI is variable and it is operator configuration, never derived from URSSAF responses or user input. | `Infrastructure/Urssaf/ApiConfiguration.php`, `UrssafEndpoints.php` |
| Insecure design (A04) | The threat model above, layers enforced by tooling, explicit state machines for registrations and payment requests, anonymisation limited to final states. | `deptrac.yaml`, `Domain/Customer/RegistrationState.php` |

### Tools that verify these countermeasures

| Tool | What it checks | Where it runs |
|---|---|---|
| PHPStan level max (strict, deprecation, Doctrine, Symfony, PHPUnit rules) | Type safety, unsafe patterns | CI `phpstan`, `make phpstan` |
| Mago lint and analyze (`--minimum-fail-level=note`) | Static analysis | CI `mago`, `make mago` |
| Deptrac | Layer boundaries inside the package | CI `deptrac`, `make deptrac` |
| PHPUnit with 100% line, method and class coverage | Behaviour including failure paths | CI `coverage`, `bin/check-coverage` |
| Infection with 100% MSI and covered MSI | The tests detect behaviour changes | CI `infection`, `make infection` |
| SonarQube Cloud (when enabled by the repository variable) | Vulnerabilities, security hotspots | CI `sonarcloud` |
| `composer audit`, `roave/security-advisories`, Dependabot | Known vulnerable dependencies | CI `composer-checks`, Dependabot |
| CodeQL on the GitHub Actions workflows | Workflow injection, excessive permissions | `.github/workflows/codeql.yml` |
| OpenSSF Scorecard | Supply-chain practices | `.github/workflows/scorecard.yml` |
| SLSA provenance attestation and `SHA256SUMS` | Provenance of release archives | `.github/workflows/release.yml` |
| GitHub secret scanning with push protection | Committed secrets | Repository setting |

## 7. Assumptions on the host application

The assurance above holds if the host application:

1. keeps `client_secret` in a secret store or environment variable (a literal in committed configuration ends up in
   the compiled container) and restricts who can read it;
2. provides a PSR-18 client that verifies TLS certificates, with safe redirect behaviour, and does not override the
   base URI with a non-HTTPS URL outside tests;
3. uses a token cache pool private to the application: the access token is stored there in clear for its lifetime;
4. protects the database (access control, encryption at rest, optionally column encryption through a custom DBAL type
   as described in [doctrine.md](../doctrine.md#personal-data-gdpr)), bounds the retention of backups and applies a
   retention and erasure policy to personal data;
5. does not log request or response bodies at its own HTTP-client level, and does not put personal data in the
   messages of exceptions raised by its own listeners and sinks.

## 8. Residual risks

The open items are tracked in the [security review](security-review-2026-11.md#findings) (SR-01 to SR-11). None is a
vulnerability in the default configuration; they are hardening opportunities and assumptions on the host.

## 9. Keeping the case valid

The case is revisited when a change touches a trust boundary: a new outbound call, a new persisted field holding
personal data, a new logging statement, a new dependency, a new workflow permission. Pull requests run the full
quality gate (`make ci`); the review document records the date and commit of the last human review.
