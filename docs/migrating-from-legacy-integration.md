# Migrating from a legacy in-house integration

A generic guide for applications that already talk to the URSSAF "API Tiers de prestations" with their own code and
store the progress as **numeric states** in their database. The mappings below are a suggestion based on a typical
legacy layout: adapt them to the meaning your own states really have.

## Principles

- Import the existing records through the package persistence (Doctrine tables `urssaf_tps_*`, see
  [doctrine.md](doctrine.md)); do not keep two sources of truth.
- Prefer going through the application layer (`RegisterCustomer`, `CreatePaymentRequest`) for records that must be
  validated again, and a one-off SQL/DBAL import for records that are already final (history).
- Never import credentials into the package tables: they come from a `CredentialsProvider` or the environment.
- Run the import per tenant, with the sandbox first.

## Customer registration states

| Legacy numeric state | Typical meaning | Package `RegistrationState` | Note |
|---|---|---|---|
| `0` | in progress, local (not sent yet) | `pending` | Will be submitted by `urssaf:customers:submit-pending`. |
| `1` | in progress, remote (sent, waiting for the customer) | `awaiting_pairing` | Needs the URSSAF customer id; if unknown, import as `pending`. |
| `2` | complete | `registered` | Needs the URSSAF customer id and the pairing acceptance date. |
| `3` | error | `error` | Becomes `pending` with a retry (`urssaf:customers:submit-pending` after a requeue) once the cause is fixed. |
| `4` | not registered | `pending` | Nothing was sent. |
| `5` | refused | `rejected` | Final; keep the legacy error codes when you have them. |
| `6` | impossible (cannot be registered) | `rejected` | Final. |
| `7` | invalid authentication | `invalid_credentials` | Then run `urssaf:requeue-invalid-credentials --tenant=...` once the credentials are fixed. |

Anything the legacy system could not tell apart (a "complete" customer that was blocked since) is corrected by the
pairing synchronisation (`blocked`, `mandate_expired`, ...) only for customers in `awaiting_pairing`, `blocked` and
`mandate_expired`: re-check imported `registered` customers once with method 020 (see the checklist).

## Payment request states

| Legacy local state | Typical meaning | Package `SubmissionState` | Note |
|---|---|---|---|
| `0` | to send | `pending` | |
| `1` | sent / integrated | `submitted` | Requires the URSSAF payment request id so that the status can be polled (method 070). |
| `2` | rejected by URSSAF | `rejected` | Final: fix the data and create a new request. |
| `3` | technical error | `error` | Retried (`pending`) or reconciled by invoice number. |
| `10` | being sent | `submitting` | Becomes stale after `stale_submission_after` and is reconciled via method 070, never resent blindly. |

The URSSAF status (10, 20, 30, 40, 50, 60, 70, 110...) of the submitted requests is **not** to be converted: it is
loaded again by the status polling (`urssaf:payment-requests:sync-statuses`) from the URSSAF id or invoice number.

## SQL sketch

Adapt table and column names. The example maps a legacy table to a staging table that you then load through DBAL or the
application layer; the real tables have embedded customer/invoice columns and JSON documents (see the mapping), so
importing aggregates through the application layer is usually simpler than raw `INSERT`s.

```sql
SELECT
    l.id AS external_reference,
    l.tenant AS tenant_id,
    CASE l.state
        WHEN 0 THEN 'pending'
        WHEN 1 THEN 'awaiting_pairing'
        WHEN 2 THEN 'registered'
        WHEN 3 THEN 'error'
        WHEN 4 THEN 'pending'
        WHEN 5 THEN 'rejected'
        WHEN 6 THEN 'rejected'
        WHEN 7 THEN 'invalid_credentials'
    END AS state
FROM legacy_customer l;

SELECT
    p.invoice_number,
    p.tenant AS tenant_id,
    CASE p.local_state
        WHEN 0 THEN 'pending'
        WHEN 1 THEN 'submitted'
        WHEN 2 THEN 'rejected'
        WHEN 3 THEN 'error'
        WHEN 10 THEN 'submitting'
    END AS state
FROM legacy_payment_request p;
```

## Checklist

- [ ] Each worker (provider) has `complement2` set to its **NOVA** (`SAP` + 9 digits) or **SIRET** (`SIR` + 14
      digits) number on every service line: the field is mandatory and the number must not be blocked
      (`ERR_FORMAT_NUMINTERVENANT`, `ERR_NUMINTERVENANT_MANQUANT`, `ERR_NUMINTERVENANT_BLOQUE`).
- [ ] Error codes: do not mix up `ERR_COMPTE_BLOQUE` (method 010: the customer account is blocked at registration) and
      `ERR_CMPT_BLOQUE` (method 050: reported when a payment request is submitted for a blocked customer account). Map
      both to the right place in your own error handling.
- [ ] Customer names are sent in ASCII (no accents): the package
      normalises them with `AsciiNameNormalizer` (uses `ext-intl` when available, a built-in map otherwise); remove
      any custom normalisation of your own to avoid double transformation.
- [ ] Before submitting a payment request for an imported customer, check the pairing with method 020
      (`urssaf:customers:sync-pairing` or `SyncCustomerPairingHandler`): only a `registered` customer is accepted by
      `CreatePaymentRequest`, and the employment period must not start before the pairing acceptance.
- [ ] Invoice numbers are unique per tenant: de-duplicate them before importing (`DuplicateInvoiceNumber`).
- [ ] Money and dates: amounts in euros with cents (the package converts to the exact wire encoding), dates as
      calendar dates in the business time zone (`Europe/Paris` by default).
- [ ] Switch the cron jobs of the old integration off before enabling the package commands, so that only one system
      sends and polls.
- [ ] Run the contract tests against the sandbox (`make test-contract`) before going to production.
