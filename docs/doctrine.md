# Doctrine persistence

The Doctrine bridge (`TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine`) persists the two aggregates with
Doctrine ORM 3 / DBAL 4 (`composer.json` declares a `conflict` with `doctrine/orm < 3.0` and `doctrine/dbal < 4.0`). The domain classes carry no Doctrine metadata: the
mapping is a set of XML files in `src/Bridge/Doctrine/Resources/mapping`.

Classes: `DoctrineCustomerRegistrationRepository` (implements `CustomerRegistrationRepository`),
`DoctrinePaymentRequestRepository` (implements `PaymentRequestRepository`), `DoctrineTransactionManager` (implements
`TransactionManager`), `DoctrineIntegration` (types, mapping driver, bundle configuration), plus the DBAL types
(`Bridge\Doctrine\Type`) and JSON serializers.

## Tables

All tables are prefixed with `urssaf_tps_` (`DoctrineIntegration::TABLE_PREFIX`).

| Table | Entity | Content |
|---|---|---|
| `urssaf_tps_customer_registrations` | `Domain\Customer\CustomerRegistration` | Registration: id, tenant, external reference, creation instant, embedded customer (civil status, contact, bank account, address) and embedded status (state, URSSAF customer id, pairing instant, last errors, update instant). |
| `urssaf_tps_payment_requests` | `Domain\PaymentRequest\PaymentRequest` | Payment request: id, tenant, external reference, submission state, submission errors (JSON), URSSAF tracking (JSON), embedded invoice (customer, billing third party, number, date, employment period, totals, lines as JSON, deposit) and embedded timeline. |
| `urssaf_tps_payment_request_progress` | `Bridge\Doctrine\Entity\PaymentRequestProgress` | Query-side row, 1:1 with `urssaf_tps_payment_requests` (same id). |

Unique constraints:

- `urssaf_tps_customer_registrations`: `(tenant_id, external_reference)` and `(tenant_id, urssaf_customer_id)`.
- `urssaf_tps_payment_requests`: `(tenant_id, invoice_number)` and `(tenant_id, external_reference)`.

## Why a progress table

The aggregate stores its URSSAF tracking (current status, history, transfer info) as one JSON document, which SQL
cannot filter on. The polling and reconciliation queries need the URSSAF id, the raw URSSAF status, the instant of the
last status change, the last poll instant and the submission state as real, indexed columns. The repository derives a
`PaymentRequestProgress` row from the aggregate and writes it with DBAL on every `save()`; DQL joins read it back
(index `tenant_id, state, urssaf_status, last_polled_at` for polling, `tenant_id, urssaf_payment_request_id` for
lookups). The row is read-only for the ORM: never write it yourself.

Polling picks the submitted requests whose status is not final, and whose status is not settled (paid/recovered) or
changed less than `settled_max_age` ago, least recently polled first.

## Registering types and mapping without Symfony

```php
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;

DoctrineIntegration::registerTypes();                     // idempotent, global DBAL type registry
$configuration->setMetadataDriverImpl(DoctrineIntegration::mappingDriver());   // SimplifiedXmlDriver
```

`DoctrineIntegration::types()` returns the custom types by name (`urssaf_tps_*`), `mappingDirectory()` the XML
directory, `MAPPING_NAMESPACE` the namespace prefix handled by the simplified driver, and
`doctrineBundleConfiguration()` the array that the Symfony bundle prepends to the `doctrine` extension.

Then build the repositories and the transaction manager from your `EntityManagerInterface`:

```php
$registrations = new DoctrineCustomerRegistrationRepository($entityManager);
$paymentRequests = new DoctrinePaymentRequestRepository($entityManager);
$transactions = new DoctrineTransactionManager($entityManager);
```

The mapped entities (`CustomerRegistration`, `PaymentRequest`, `PaymentRequestProgress`) are not declared `final` so that
Doctrine can generate their lazy-loading proxies with any `auto_generate_proxy_classes` mode (including pre-generation at
cache warm-up); they are final by convention (`@final`) and must not be extended. Value objects are typed properties
mapped as embeddables and custom DBAL types (`DoctrineIntegration::types()`).

## Dedicated entity manager (strongly recommended)

The repositories `persist()` and then `flush()` the entity manager they are built with on **every** `save()`. A Doctrine
flush has no per-entity variant (`flush($entity)` was removed in ORM 3): it writes **all** the pending changes of the
unit of work, host entities included. If the package shares the entity manager of your application, a `save()` made by
the package would therefore also flush half-finished changes of your own entities (and a rollback of the package
transaction would roll them back too).

Use a **dedicated entity manager** (and connection) for the package tables, and point the bundle at it:

```yaml
doctrine:
    dbal:
        connections:
            default: { url: '%env(resolve:DATABASE_URL)%' }
            urssaf:  { url: '%env(resolve:URSSAF_DATABASE_URL)%' }   # same database is fine, different connection
    orm:
        entity_managers:
            default: { connection: default, mappings: { App: { type: attribute, dir: '%kernel.project_dir%/src/Entity', prefix: App\Entity } } }
            urssaf:  { connection: urssaf }                         # the bundle adds the package mapping to this one

urssaf_tiers_prestations:
    persistence: { driver: doctrine, entity_manager: urssaf }
```

With a non-default `persistence.entity_manager` the bundle attaches the package mapping to that entity manager only, so
the host entities and the package entities never share a unit of work. When you must share the entity manager, flush
your own changes before calling a package handler, and accept that the handler flushes whatever is pending.

`DoctrineTransactionManager` never calls `EntityManager::clear()` (see below), so even on a shared entity manager a
failed package operation does not detach your entities.

## Personal data (GDPR)

The customer registration table holds personal data of private individuals (identity, birth place, contact details,
IBAN/BIC and account holder, postal address); the payment request table holds the customer identifiers and invoice
lines. The package gives you the tools, the policy is yours:

1. **Retention.** Define how long a registration is kept after it is no longer needed (for example a fixed delay after
   it was `rejected`, or after the last payment request of the customer reached a final status) and run a scheduled job
   in your application that selects the expired records.
2. **Erasure by anonymisation.** `CustomerRegistration::anonymise(Instant $now)` is allowed on registrations in a final
   state (`rejected`). It replaces the customer (identity, contact details, bank account, address) with the fixed
   anonymised customer `Customer::anonymised()`, keeps the technical data (id, tenant, external reference, state,
   URSSAF customer id, timestamps) and records `CustomerRegistrationAnonymised`. It is idempotent; any other state
   throws `InvalidStateTransition`. Save the aggregate with the repository afterwards.
3. **Erasure by deletion.** `CustomerRegistrationRepository::delete(RegistrationId)` removes the row (no domain event,
   nothing happens when it does not exist); `PaymentRequestRepository::delete(LocalPaymentRequestId)` does the same for a
   payment request. Prefer `anonymise()` when you need to keep the history (reporting, audit), `delete()` otherwise.
   Neither call erases anything at URSSAF: the data they hold is subject to their own retention rules.
4. **Encryption at rest.** The personal values are stored through custom DBAL types (`PersonNameType`, `IbanType`,
   `BicType`, `AccountHolderType`, `EmailAddressType`, `MobilePhoneNumberType`, the address and birth place types...),
   registered under stable names (`IbanType::NAME`...). These types are `final`: to encrypt a column, write a type of
   your own that wraps the package one and register it under the same name, after
   `DoctrineIntegration::registerTypes()`:

   ```php
   final class EncryptedIbanType extends Type
   {
       public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
       {
           // The mapping declares the clear-text length (34 for an IBAN): widen the column for the ciphertext.
           return $platform->getStringTypeDeclarationSQL(array_replace($column, ['length' => 255]));
       }

       public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
       {
           $plain = (new IbanType())->convertToDatabaseValue($value, $platform);

           return $plain === null ? null : $this->cipher()->encrypt($plain);
       }

       public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Iban
       {
           return (new IbanType())->convertToPHPValue($value === null ? null : $this->cipher()->decrypt((string) $value), $platform);
       }
   }

   Type::overrideType(IbanType::NAME, EncryptedIbanType::class);
   ```

   Use authenticated encryption with a key kept outside the database (KMS, Symfony secrets), check in the generated migration that the
   column is wide enough for the ciphertext, and remember that an encrypted column can no longer be
   searched or constrained by the database (the unique constraints of the package use the external reference and the
   URSSAF customer id, which are not personal data).
5. **Backups and logs.** Backups keep the data that was erased afterwards: bound their retention and replay the erasures
   after a restore. The package never logs personal data, and the value objects mask it in `var_dump()`.

## Migrations

The package ships **no migration**: the schema depends on your platform and on the rest of your schema. Generate it in
your application:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

(or `doctrine:schema:update --dump-sql` for a quick look). Re-run `diff` after each upgrade of the package: the mapping
may evolve (see the changelog).

## Transactions

`DoctrineTransactionManager::transactional()` runs the operation in a DBAL transaction. The repositories flush on every
save, so those flushes join the surrounding transaction and become durable together. On failure the transaction is
rolled back, the package entities still managed by the entity manager (`CustomerRegistration`, `PaymentRequest`,
`PaymentRequestProgress`) are detached so that none of them keeps a state that was rolled back, and the throwable is
rethrown unchanged. The entity manager is neither cleared nor closed: the entities of your application stay managed
(domain exceptions are ordinary control flow). Domain events are published by `UnitOfWork` only after the commit.

Because every repository `save()` flushes (see [Dedicated entity manager](#dedicated-entity-manager-strongly-recommended)),
changes to host entities pending in the same entity manager are written, and rolled back, with the package
transaction: use a dedicated entity manager.

If a handler runs inside a transaction opened by your application, the package transaction becomes a nested one
(a savepoint when the platform supports it): its changes become durable only when your outer transaction commits, but
the domain events are published when the nested transaction returns, i.e. before that commit. See the outbox pattern in
[workflow.md](workflow.md#events-outbox-pattern-and-safety-crons).

## Claim semantics

`claimForSubmission(id, now): bool` is the concurrency guard of the submission use cases. It is one conditional
`UPDATE ... SET state = 'submitting' ... WHERE id = :id AND state = 'pending'`: exactly one worker wins (returns
`true`); the others get `false` and the handler reports `not_claimed`. It records no domain event, never loads the
aggregate (the managed copy is detached, so call `get()` again to read the claimed row) and, for payment requests,
also records the submission start instant (used for stale detection) and updates the progress row in the same
transaction.

A record left in `submitting` (worker crash, lost answer) is never resubmitted blindly: see the stale reconciliation
in [workflow.md](workflow.md).

## Uniqueness error codes

`save()` translates `UniqueConstraintViolationException` into an `InvariantViolation` (a `UrssafException`) with a
stable code:

| Code | Constraint |
|---|---|
| `customer_registration.duplicate_external_reference` | `(tenant_id, external_reference)` of the registrations |
| `customer_registration.duplicate_urssaf_customer_id` | `(tenant_id, urssaf_customer_id)` of the registrations |
| `payment_request.duplicate_invoice_number` | `(tenant_id, invoice_number)` of the payment requests |
| `payment_request.duplicate_external_reference` | `(tenant_id, external_reference)` of the payment requests |

The use cases check these conditions first and raise the friendlier `CustomerAlreadyRegistered` /
`DuplicateInvoiceNumber`; the constraint is the safety net against concurrent inserts.
