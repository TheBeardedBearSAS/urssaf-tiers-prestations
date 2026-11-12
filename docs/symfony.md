# Symfony bundle

Bundle class: `TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\UrssafTiersPrestationsBundle` (alias
`urssaf_tiers_prestations`), for Symfony 6.4 and 7.x. Register it in `config/bundles.php`.

## Configuration reference

The configuration tree is defined in `Bridge\Symfony\DependencyInjection\BundleConfiguration`.

| Key | Type | Default | Description |
|---|---|---|---|
| `environment` | enum `sandbox` \| `production` | required | Target platform (`https://api-edi.urssaf.fr` / `https://api.urssaf.fr`). |
| `base_uri` | string \| null | `null` | Overrides the base URI of the selected environment (the sandbox host may differ). |
| `application_id` | string \| null | `null` | Value of the `recouv.application.id` header (omitted when null). |
| `token_safety_margin` | int >= 0 | `60` | Seconds before expiry from which an access token is renewed. |
| `pairing_time_zone` | string | `Europe/Paris` | Time zone of the pairing acceptance date that URSSAF writes (French local time) in the method 020 status description; validated against `DateTimeZone::listIdentifiers()`. |
| `http_client` | string \| null | `null` | Service id of a PSR-18 client. Default: a dedicated Symfony HttpClient wrapped in `Psr18Client`. |
| `http_timeout` | float >= 0.1 | `15.0` | Timeout in seconds of the default HTTP client. |
| `credentials_provider` | string \| null | `null` | Service id of a `CredentialsProvider`. Mutually exclusive with `credentials`. |
| `credentials` | map tenant id => `{client_id, client_secret}` | `[]` | Static credentials by tenant (both keys required, non-empty). Use `%env(...)%` placeholders. |
| `tenants` | list of string | keys of `credentials` | Tenants processed by the commands that cannot discover them. |
| `token_store.cache_pool` | string | `cache.app` | Cache pool (PSR-6) that stores the access tokens. Recommended: a **dedicated pool** (see below), so that clearing `cache.app` does not drop the tokens and the tokens never share a pool with unrelated data. |
| `token_store.service` | string \| null | `null` | Service id of a `TokenStore`; takes precedence over the cache pool. |
| `gateway.simulated` | bool | `false` | Tests only: replaces the URSSAF API with the in-memory `Testing\SimulatedUrssafGateway`, exposed as a public service to script the scenarios (see [testing.md](testing.md)). Refused with `environment: production`. |
| `rate_limiter.enabled` | bool | `false` | Enforces the URSSAF quotas per tenant, in the current process (`SlidingWindowRateLimiter`). |
| `workflow.business_timezone` | string | `Europe/Paris` | Time zone used for date-only values (validated against `DateTimeZone::listIdentifiers()`). |
| `workflow.settled_max_age` | ISO 8601 duration | `P90D` | How long a paid/recovered request is still polled. |
| `workflow.unknown_status_max_age` | ISO 8601 duration | `P30D` | How long a payment request is still polled after a status code unknown to this package version was observed (it may never change). |
| `workflow.max_polling_age` | ISO 8601 duration | `P1Y` | How long a payment request is still polled after its submission, whatever its status: a request is never followed forever. |
| `workflow.stale_submission_after` | ISO 8601 duration | `PT15M` | Age after which a `submitting` request is considered stale. |
| `workflow.billing_third_party_id` | string | `menage.fr` | Billing third-party id sent with every payment request. |
| `workflow.batch_size` | int 1..10 | `10` | Payment requests per method 050 call (URSSAF accepts 1 to 10). |
| `workflow.default_page_size` | int >= 1 | `100` | Records loaded by a batch use case when no limit is given. |
| `workflow.max_page_size` | int >= 1 | `1000` | Upper bound of the records loaded by a batch use case. |
| `workflow.max_submission_attempts` | int >= 1 | `5` | Submission attempts after which a technical failure is no longer requeued (left in `error` for a manual action). |
| `persistence.driver` | enum `in_memory` \| `doctrine` \| `custom` | `doctrine` if `doctrine/orm` is installed, else `in_memory` | Persistence adapter. |
| `persistence.workflow_statistics` | string \| null | `null` | Service id of your `WorkflowStatistics` (driver `custom`, optional: without it `GetWorkflowOverviewHandler` and `urssaf:status` are not registered). |
| `persistence.entity_manager` | string | `default` | Entity manager that gets the mapping (driver `doctrine`). **Use a dedicated entity manager** (see [Doctrine](#doctrine)): the repositories flush the whole unit of work of the entity manager they use. |
| `persistence.customer_registration_repository` | string \| null | `null` | Service id of your `CustomerRegistrationRepository` (driver `custom`). |
| `persistence.payment_request_repository` | string \| null | `null` | Service id of your `PaymentRequestRepository` (driver `custom`). |
| `lock.enabled` | `auto` \| bool | `auto` | Non-blocking lock per command / handler and tenant (see [Locks](#locks-against-overlapping-runs)). `auto`: enabled when `symfony/lock` is installed and the factory service exists; `true`: required (the container fails to build without the factory service, or without `symfony/lock`); `false`: disabled. |
| `lock.factory` | string | `lock.factory` | Service id of a `Symfony\Component\Lock\LockFactory`. |
| `lock.ttl` | float >= 1 | `3600.0` | Seconds after which the lock of a crashed process expires. Set it above the longest run of a command. |
| `outbox.enabled` | bool | `false` | Writes the integration events in an outbox, in the transaction of each state change (see [integration-events.md](integration-events.md)). |
| `outbox.service` | string \| null | `null` | Service id of your `Outbox` (required with the `custom` driver). Default: the outbox of the persistence driver. |
| `outbox.sink` | string | `messenger` | `messenger`, `event_dispatcher`, or the service id of an `IntegrationEventSink`. |
| `outbox.bus` | string | `messenger.default_bus` | Bus of the `messenger` sink. |
| `outbox.retention` | ISO 8601 duration | `P30D` | How long a dispatched entry is kept (`urssaf:outbox:purge`). |
| `scheduler.enabled` | bool | `false` | Registers a Symfony Scheduler schedule that runs the crons of the package (see [Scheduler](#scheduler)). Requires `symfony/scheduler`. |
| `scheduler.name` | string | `urssaf` | Name of the schedule (transport `scheduler_<name>`). |
| `scheduler.frequencies.submit_pending` | ISO 8601 duration \| null | `PT5M` | `urssaf:customers:submit-pending` and `urssaf:payment-requests:submit-pending`; null leaves the task out. |
| `scheduler.frequencies.sync_pairing` | ISO 8601 duration \| null | `PT10M` | `urssaf:customers:sync-pairing`. |
| `scheduler.frequencies.sync_statuses` | ISO 8601 duration \| null | `PT15M` | `urssaf:payment-requests:sync-statuses`. |
| `scheduler.frequencies.reconcile_stale` | ISO 8601 duration \| null | `PT15M` | `urssaf:customers:reconcile-stale` and `urssaf:payment-requests:reconcile-stale`. |
| `scheduler.frequencies.relay_outbox` | ISO 8601 duration \| null | `PT1M` | `urssaf:outbox:relay` (only with the outbox). |
| `scheduler.frequencies.purge_outbox` | ISO 8601 duration \| null | `P1D` | `urssaf:outbox:purge` (only with the outbox). |
| `messenger.enabled` | bool | `false` | Registers the Messenger handlers. |
| `messenger.bus` | string | `messenger.default_bus` | Bus used by the auto-submit listener. |
| `messenger.auto_submit` | bool | `false` | Dispatches the submit messages when a registration / payment request is created. |

Validation rules enforced at container build time:

- `credentials` and `credentials_provider` are mutually exclusive, and one of them is required.
- `messenger.auto_submit` requires `messenger.enabled`.
- `gateway.simulated` cannot be used with `environment: production`.
- `outbox.enabled` with the `custom` driver requires `outbox.service`.
- `lock.enabled: true` requires `symfony/lock`.
- `scheduler.enabled: true` requires `symfony/scheduler`.
- `workflow.default_page_size` must not exceed `workflow.max_page_size`.
- The `custom` driver requires both repository service ids.
- `business_timezone` must be a known time zone and the durations (`settled_max_age`, `unknown_status_max_age`, `max_polling_age`, `stale_submission_after`) valid ISO 8601 intervals.

The `in_memory` driver keeps everything in the process memory: use it for tests only.

### Dedicated cache pool for the access tokens

The access tokens are stored in the `token_store.cache_pool` pool (`cache.app` by default). Declare a dedicated pool, so
that `cache:pool:clear cache.app` (a routine deployment step) does not force a new token request for every tenant, and
so that the tokens live in an adapter you control (Redis with authentication, for instance):

```yaml
framework:
    cache:
        pools:
            urssaf.token_cache:
                adapter: cache.adapter.redis
                provider: '%env(REDIS_DSN)%'

urssaf_tiers_prestations:
    token_store: { cache_pool: urssaf.token_cache }
```

The pool must be shared by every process that calls URSSAF (web, workers, cron): a per-process `array` adapter would
request a token per run. Tokens are secrets: do not expose the pool through a cache dashboard or a shared namespace.

## Console commands

Tenants of a command are processed one after the other; every command (except `--dry-run`) takes a lock per tenant when
the lock is enabled: an overlapping run prints `Already running (lock "urssaf_tps.<operation>.<tenant>" is held): skipped.`
and exits with `0` (see [Locks](#locks-against-overlapping-runs)).

All commands accept `--limit=N` (positive integer, capped by the workflow policy) and `--dry-run` (lists what would be
done without calling URSSAF or changing anything), and print a table of the processed items followed by the count of
each outcome.

| Command | Purpose | `--tenant` |
|---|---|---|
| `urssaf:customers:submit-pending` | Submit the pending registrations (method 010). | optional, default: configured `tenants` |
| `urssaf:customers:sync-pairing` | Poll the pairing status of the registrations awaiting pairing, blocked or mandate expired, least recently updated first (method 020). | optional, default: every tenant with such registrations |
| `urssaf:customers:reconcile-stale` | Settle the registrations left `submitting` for `stale_submission_after`: URSSAF offers no way to look them up, so each one is moved to `error` (cause `urssaf.submission_outcome_unknown`, outcome `manual_review`) and never resent automatically; an operator checks the customer and retries it. | none (all tenants) |
| `urssaf:payment-requests:submit-pending` | Submit the pending payment requests (method 050), batches of `batch_size`. | optional, default: configured `tenants` |
| `urssaf:payment-requests:sync-statuses` | Poll the status of the submitted payment requests (method 070). | optional, default: every tenant with requests to poll |
| `urssaf:payment-requests:reconcile-stale` | Reconcile the stale `submitting` payment requests: searches them at URSSAF (method 070); found => `submitted`, proven absent => back to `pending` for the next submit run. The command itself never sends a request. | none (all tenants) |
| `urssaf:requeue-invalid-credentials` | Move the records refused for invalid credentials back to `pending`, once the credentials are fixed. | required |
| `urssaf:outbox:relay` | Deliver the pending integration events (exit `1` when a delivery failed). | none |
| `urssaf:outbox:purge` | Delete the outbox entries dispatched for longer than `outbox.retention`. | none |
| `urssaf:status` | Count the registrations and payment requests by tenant and state (local database only); `--format=json` for monitoring. | optional, default: every tenant with records |
| `urssaf:check` | Check the installation (see below); `--skip-remote` does not call URSSAF. | optional, default: configured `tenants` |

Exit codes: `0` on success; `1` when an item ended `failed`, `invalid_credentials` or `unconfirmed`, or when a package
exception (`UrssafException`) or an invalid option (for example `--limit=0`, a missing `--tenant`, or no tenant to
process) stops the command. The message is printed as an error.

Outcomes (`Application\Dto\ItemOutcome`): `not_found`, `not_claimed`, `accepted`, `rejected`, `invalid_credentials`,
`requeued`, `exhausted`, `unconfirmed`, `manual_review`, `changed`, `unchanged`, `unknown_status`, `ignored`, `failed`, `reconciled`.

### Checking the installation

`urssaf:check` prints one line per check and exits with `1` when one of them fails (warnings do not fail): run it after
each deployment, or in the CI of the application with `--skip-remote`.

| Check | Failure / warning |
|---|---|
| `credentials` | failure: no credentials for a tenant in the configured provider |
| `authentication` | failure: URSSAF refuses the credentials or cannot be reached. A new access token is requested for every tenant (any cached one is ignored), the tokens of the application are not touched. Skipped with `--skip-remote`; replaced by a `gateway` warning when `gateway.simulated` is enabled |
| `token store` | warning: the tokens are stored in `cache.app` instead of a dedicated pool |
| `database schema` | failure (driver `doctrine`): the package tables are missing or differ from the mapping; warning: the entity manager is the `default` one |
| `lock` | warning: the lock is disabled or `symfony/lock` is missing |

Add checks of your own by implementing `Bridge\Symfony\Diagnostic\InstallationCheck` (autoconfigured, tag
`urssaf_tiers_prestations.installation_check`): they run after the package checks, unless their tag has a positive
`priority`.

## Messenger

With `messenger.enabled: true`, the following messages (namespace `Bridge\Symfony\Message`) are handled by the handlers
of `Bridge\Symfony\MessageHandler`. They are plain `final readonly` classes holding scalars, so they serialize with any
Messenger serializer.

| Message | Properties | Effect |
|---|---|---|
| `SubmitCustomerRegistrationMessage` | `registrationId` | Sends one pending registration (method 010). |
| `SubmitPaymentRequestsMessage` | `tenantId`, `paymentRequestIds` (list) | Sends the given payment requests of one tenant (method 050). |
| `SyncTenantCustomerPairingMessage` | `tenantId` | Polls the pairing status of the tenant's registrations (method 020). |
| `SyncTenantPaymentStatusesMessage` | `tenantId` | Polls the status of the tenant's submitted payment requests (method 070). |

Failure mapping (`MessageFailures`):

- `RetryableException` (URSSAF unavailable, rate limit exceeded...) => `RecoverableMessageHandlingException`: the
  message is retried by the retry strategy of the transport, with the delay requested by URSSAF (`Retry-After`) or by
  the local rate limiter when known. The delay needs a symfony/messenger version whose
  `RecoverableMessageHandlingException` accepts it; on older versions (6.4) the exception carries no delay and the retry
  strategy of the transport alone decides.
- Operation already running for the tenant (lock held) => `RecoverableMessageHandlingException` with a 30 s delay: the
  message is retried later instead of being lost or run concurrently (see [Locks](#locks-against-overlapping-runs)).
- Any other `UrssafException` => `UnrecoverableMessageHandlingException`: retrying the same message cannot succeed
  (it goes straight to the failure transport).
- Failures of other origins (database down...) are left to the retry strategy of the host.

### auto_submit

With `messenger.auto_submit: true`, an `AutoSubmitListener` listens to the domain events
`CustomerRegistrationRequested` and `PaymentRequestCreated` and dispatches `SubmitCustomerRegistrationMessage` /
`SubmitPaymentRequestsMessage` on the configured bus. Domain events are published after the transaction commit, so the
consumer always sees the record. The pairing and status polling are still driven by cron (commands) or by your own
scheduler dispatching the `SyncTenant*` messages.

## Scheduler

Instead of the crontab, the bundle can register a [Symfony Scheduler](https://symfony.com/doc/current/scheduler.html)
schedule that runs the same console commands, through Messenger (`Symfony\Component\Console\Messenger\RunCommandMessage`,
handled by the framework): same locks, same reports, same safety nets.

```bash
composer require symfony/scheduler
```

```yaml
urssaf_tiers_prestations:
    scheduler:
        enabled: true
        frequencies: { submit_pending: PT5M, sync_pairing: PT10M, sync_statuses: PT15M, reconcile_stale: PT15M }
```

Run one worker for the schedule (and keep it running, like any Messenger worker):

```bash
php bin/console messenger:consume scheduler_urssaf
```

A command that exits with a non-zero code makes its message fail (`RunCommandFailedException`), so it shows in the
worker logs and in the failure transport. Run a single scheduler worker (or enable the lock): two workers of the same
schedule would run each task twice.

## Locks against overlapping runs

Two crons that overlap, a cron racing a Messenger worker, or two workers handling the same tenant could poll or submit
the same records at the same time. The atomic claims keep that safe (a record is never sent twice) but wasteful, and
URSSAF quotas are per tenant. When `symfony/lock` is installed, every console command and Messenger handler takes a
**non-blocking** lock named `urssaf_tps.<operation>.<scope>` before it works, and releases it afterwards:

| Operation | Used by | Scope |
|---|---|---|
| `customers.submit` | `urssaf:customers:submit-pending` | tenant |
| `customers.submit` | `SubmitCustomerRegistrationMessage` | registration id |
| `payment-requests.submit` | `urssaf:payment-requests:submit-pending`, `SubmitPaymentRequestsMessage` | tenant |
| `customers.sync-pairing` | `urssaf:customers:sync-pairing`, `SyncTenantCustomerPairingMessage` | tenant, or `*` without `--tenant` |
| `payment-requests.sync-statuses` | `urssaf:payment-requests:sync-statuses`, `SyncTenantPaymentStatusesMessage` | tenant, or `*` without `--tenant` |
| `payment-requests.reconcile-stale` | `urssaf:payment-requests:reconcile-stale` | `*` |
| `customers.reconcile-stale` | `urssaf:customers:reconcile-stale` | `*` |
| `requeue-invalid-credentials` | `urssaf:requeue-invalid-credentials` | tenant |
| `outbox.relay` | `urssaf:outbox:relay` | `*` |

When the lock is already held: a **command** prints that the operation is already running and exits with `0` (the run
that holds the lock does the work); a **Messenger handler** throws `RecoverableMessageHandlingException` with a 30 s delay,
so the message is retried by the transport's retry strategy. `--dry-run` takes no lock.

A run without `--tenant` (scope `*`) and a run for one tenant use different locks and may overlap; schedule one or the
other. A tenant that is busy is skipped, the other tenants of the same run are still processed.

Setup:

```bash
composer require symfony/lock
```

```yaml
framework:
    lock: '%env(LOCK_DSN)%'   # redis://..., or `flock`; must be shared by every host that runs the crons / workers

urssaf_tiers_prestations:
    lock: { enabled: auto, factory: lock.factory, ttl: 3600 }
```

The store must be visible to all the processes that may overlap: `flock` only protects processes of the same machine,
use a Redis / database / Zookeeper store otherwise. `ttl` bounds how long the lock of a crashed process blocks the
operation. Without `symfony/lock` (or with `enabled: false`) everything just runs, protected by the claims only.

## Doctrine

When the driver is `doctrine` and DoctrineBundle is registered, the bundle prepends the Doctrine configuration: the
custom DBAL types and the XML mapping (see [doctrine.md](doctrine.md)). With a non-default `entity_manager`, the mapping
is attached to that entity manager only.

**Use a dedicated entity manager** (`persistence.entity_manager: urssaf`, with its own DBAL connection). The repositories
`flush()` on every save and a Doctrine flush writes *all* pending changes of the entity manager: on a shared entity
manager, a package call would also flush (and, on failure, roll back) half-finished changes of your own entities.
`DoctrineTransactionManager` detaches only the package entities after a rollback and never clears your entities.

The bundle does not touch `doctrine.orm.auto_generate_proxy_classes`: the mapped entities are not `final` (final by
convention), so the proxy cache warmer can pre-generate their proxies in every mode.

**Native lazy objects (PHP 8.4+).** On PHP 8.4 or higher, with an ORM that implements them (3.4+), the bundle also
prepends `doctrine.orm.enable_native_lazy_objects: true`: the ORM then uses PHP lazy objects instead of generated
proxies, which is required when `symfony/var-exporter` 8 is installed (it no longer ships the lazy ghosts the ORM
proxies rely on; without it the ORM throws "Symfony LazyGhost is not available"). It is only a default: your own
`doctrine` configuration wins, for instance `enable_native_lazy_objects: false` with `symfony/var-exporter` 6.4 or 7. On
PHP 8.2 and 8.3 nothing is prepended.

## Validation and forms

The use cases validate their input themselves and throw `ValidationFailed` with every violation at once (code
`<subject>.<reason>`, path such as `invoice.lines[0].quantity`, scalar params). When `symfony/validator` is installed, the
bundle registers the public service `Bridge\Symfony\Validation\ViolationListConverter`, which turns them into a Symfony
`ConstraintViolationList`:

| `ConstraintViolation` | Value |
|---|---|
| `getPropertyPath()` | the violation path, with the optional prefix (`customer.birthDate`) |
| `getCode()`, `getMessageTemplate()` | the violation code (`birth_date.in_future`) |
| `getMessage()` | the translation of the code in the domain `urssaf_tiers_prestations` (current locale of the `translator`), or the English message without translator |
| `getParameters()` | the violation params as `%name%` placeholders |
| `getCause()` | the original `Domain\Validation\Violation` |
| `getInvalidValue()` | always `null`: the rejected raw values (personal data) are never exposed |

`convert()` keeps the errors only; `convertWithWarnings()` keeps the warnings too.

The drafts (`Domain\Customer\Input\CustomerDraft`, `Domain\PaymentRequest\Input\PaymentRequestDraft` and their nested
drafts) are plain readonly objects with nullable properties: the Serializer builds them from a request body
(`#[MapRequestPayload]`, or `$serializer->deserialize($json, PaymentRequestDraft::class, 'json')`). The list of service
lines needs a PHPDoc type extractor (`symfony/property-info` with `phpstan/phpdoc-parser`, enabled by default in a
Symfony application). Missing JSON keys become `null` and are reported by the use case as `field.required`.

An API controller:

```php
#[Route('/customers/{reference}/payment-requests', methods: ['POST'])]
public function create(
    string $reference,
    #[MapRequestPayload] PaymentRequestDraft $draft,
    CreatePaymentRequestHandler $createPaymentRequest,
    ViolationListConverter $violations,
): Response {
    try {
        $id = $createPaymentRequest(new CreatePaymentRequest(
            TenantId::fromString('tenant-1'),
            CustomerSelector::byExternalReference(ExternalReference::fromString($reference)),
            $draft,
        ));
    } catch (ValidationFailed $failed) {
        // API Platform / Problem Details style: the list serializes with the "constraint_violation_list" normalizer.
        throw new ValidationFailedException($draft, $violations->convert($failed));
    }

    return $this->json(['id' => $id->value()], Response::HTTP_CREATED);
}
```

With a form, map each violation on the form (the paths follow the property paths of the draft):

```php
foreach ($violations->convert($failed) as $violation) {
    $form->addError(new FormError((string) $violation->getMessage(), $violation->getMessageTemplate(), $violation->getParameters(), null, $violation));
}
```

or let the Form component place them on the matching fields with
`(new ViolationMapper())->mapViolation($violation, $form)` for each violation, the form data being the draft.

## Translations

The violation codes (`<subject>.<reason>`, for example `birth_date.in_future`) are translatable messages of the
translation domain `urssaf_tiers_prestations`. The bundle ships English and French:
`Resources/translations/urssaf_tiers_prestations.en.yaml` and `urssaf_tiers_prestations.fr.yaml` (same keys, kept in
sync by the test suite).

To add another locale: create `translations/urssaf_tiers_prestations.<locale>.yaml` in your application, with the same
keys as the English file. To reword a shipped message, define the key in your application's file of that locale:

```yaml
# translations/urssaf_tiers_prestations.fr.yaml
"customer_registration.not_found": "Ce client n'a pas encore été inscrit."
```

Keys defined in your application's `translations/` directory override the shipped messages. Translate a
violation with `$translator->trans($violation->code(), [], 'urssaf_tiers_prestations')` (the message keys are the
violation codes; `Violation::params()` holds the scalar parameters of the violation).
