<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use DateInterval;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestCreated;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestRejectedByUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestSubmissionFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\InvoiceRules;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Aggregate root: a payment request of a tenant, from its local validation to its final URSSAF status.
 *
 * Lifecycle: see {@see SubmissionState}. Once submitted, {@see self::applyUrssafStatus()} follows the URSSAF status
 * (method 070) until it is final, or settled for longer than the configured maximum age.
 *
 * A stale "submitting" request (worker crash, timeout without answer) is never reset automatically: URSSAF may have
 * integrated it, and sending it again would answer ERR_FACTURE_DOUBLON. It must be reconciled by searching its invoice
 * number (method 070): {@see self::reconcileAsSubmitted()} when found, {@see self::markFailed()} then
 * {@see self::retry()} when not.
 *
 * Submission attempts are counted (pending -> submitting); {@see self::retry()} refuses to requeue a failed request
 * once the maximum number of attempts is reached: it then stays in error until handled manually.
 *
 * Not final only because Doctrine generates lazy-loading proxies by extending the mapped entities: final by
 * convention, never extend it. For the same reason the identifier is neither readonly nor private: a proxy is created
 * with its identifier, which Doctrine assigns again (another instance of the same value) when the proxy is loaded. It is
 * never reassigned otherwise.
 *
 * The root exposes the whole state read by the persistence and wire mappers, hence the expected method count.
 *
 * @final
 *
 * @mago-expect lint:too-many-methods
 */
class PaymentRequest
{
    /**
     * Default maximum number of submission attempts before {@see self::retry()} refuses to requeue.
     */
    public const DEFAULT_MAX_ATTEMPTS = 5;

    private SubmissionState $state = SubmissionState::Pending;

    private int $attempts = 0;

    /** @var list<SubmissionError> */
    private array $submissionErrors = [];

    private UrssafTracking $tracking;

    private Timeline $timeline;

    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        protected LocalPaymentRequestId $id,
        private readonly TenantId $tenantId,
        private readonly ExternalReference $externalReference,
        private readonly Invoice $invoice,
        Instant $createdAt,
    ) {
        $this->tracking = UrssafTracking::none();
        $this->timeline = Timeline::startedAt($createdAt);
    }

    /**
     * Creates a pending payment request. Prefer {@see PaymentRequestFactory}, which also checks the time-dependent
     * rules and reports every violation of a draft.
     *
     * @throws ValidationFailed when the invoice breaks an intrinsic rule ({@see InvoiceRules::intrinsic()})
     */
    public static function create(
        LocalPaymentRequestId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        Invoice $invoice,
        Instant $now,
    ): self {
        InvoiceRules::intrinsic()->validate($invoice)->throwIfInvalid();
        $request = new self($id, $tenantId, $externalReference, $invoice, $now);
        $request->events[] = new PaymentRequestCreated($id, $tenantId, $invoice->number(), $now);

        return $request;
    }

    /**
     * pending -> submitting: one more attempt, started now (see {@see self::lastAttemptAt()}).
     *
     * @throws InvalidStateTransition
     */
    public function startSubmission(Instant $now): void
    {
        $this->state = $this->state->transitionTo(SubmissionState::Submitting);
        $this->timeline = $this->timeline->submissionStarted($now);
        ++$this->attempts;
    }

    /**
     * submitting -> submitted: method 050 integrated the request. Records {@see PaymentRequestSubmitted}.
     *
     * @param PaymentRequestStatus|string $status status returned by method 050 (raw "statut"; blank = unknown)
     *
     * @throws InvalidStateTransition
     */
    public function markAccepted(PaymentRequestId $urssafId, PaymentRequestStatus|string $status, Instant $now): void
    {
        $this->state = $this->state->transitionTo(SubmissionState::Submitted);
        $this->acknowledge($urssafId, $status, $now);
    }

    /**
     * submitting | error -> submitted: a search by invoice number found the request at URSSAF.
     * Records {@see PaymentRequestSubmitted}.
     *
     * @throws InvalidStateTransition
     */
    public function reconcileAsSubmitted(PaymentRequestId $urssafId, PaymentRequestStatus|string $status, Instant $now): void
    {
        $this->state = $this->state->reconciledAsSubmitted();
        $this->acknowledge($urssafId, $status, $now);
    }

    /**
     * submitting -> rejected: business errors returned by method 050. Records {@see PaymentRequestRejectedByUrssaf}.
     *
     * @param list<SubmissionError>       $errors
     * @param PaymentRequestStatus|string $status raw "statut" returned by method 050 (usually an error code)
     *
     * @throws InvalidStateTransition
     */
    public function markRejected(array $errors, PaymentRequestStatus|string $status, Instant $now): void
    {
        $this->moveTo(SubmissionState::Rejected, $errors, $now);
        $this->tracking = $this->tracking->observe($status, $now);
        $this->events[] = new PaymentRequestRejectedByUrssaf($this->id, $this->tenantId, $this->externalReference, $errors, $now);
    }

    /**
     * submitting -> error: technical failure, described by the errors (e.g. the translated cause of a transport
     * error). Records {@see PaymentRequestSubmissionFailed}.
     *
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    public function markFailed(array $errors, Instant $now): void
    {
        $this->moveTo(SubmissionState::Error, $errors, $now);
        $this->events[] = new PaymentRequestSubmissionFailed($this->id, $this->tenantId, $errors, $now);
    }

    /**
     * submitting -> invalid_credentials.
     *
     * @throws InvalidStateTransition
     */
    public function markInvalidCredentials(Instant $now): void
    {
        $this->moveTo(SubmissionState::InvalidCredentials, [], $now);
    }

    /**
     * error | invalid_credentials -> pending. The submission errors are kept until the next attempt completes.
     *
     * A technical failure (error) is requeued only while fewer than maxAttempts attempts were made (see
     * {@see self::canRetry()}); refused credentials are always requeued (the credentials, not the request, were at
     * fault).
     *
     * @param positive-int $maxAttempts
     *
     * @throws InvalidStateTransition
     * @throws InvariantViolation     "payment_request.max_attempts_reached"
     */
    public function retry(Instant $now, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): void
    {
        if (!$this->canRetry($maxAttempts)) {
            throw InvariantViolation::because(
                'payment_request.max_attempts_reached',
                \sprintf('The payment request failed %d times: it is not retried automatically any more.', $this->attempts),
                ['attempts' => $this->attempts, 'max_attempts' => $maxAttempts],
            );
        }
        $this->moveTo(SubmissionState::Pending, $this->submissionErrors, $now);
    }

    /**
     * False for a request in error once maxAttempts submission attempts were made; true otherwise (the transition
     * itself is checked by {@see self::retry()}).
     *
     * @param positive-int $maxAttempts
     */
    public function canRetry(int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): bool
    {
        return $this->state !== SubmissionState::Error || $this->attempts < $maxAttempts;
    }

    /**
     * Applies the result of a poll (method 070) to a submitted request: always records the poll instant and replaces
     * the transfer / rejection information; records {@see PaymentRequestStatusChanged} only when the raw status
     * differs from the current one (a blank status is ignored).
     *
     * @throws InvalidStateTransition when the request is not submitted
     */
    public function applyUrssafStatus(
        PaymentRequestStatus|string $status,
        ?TransferInfo $transfer,
        ?Rejection $rejection,
        Instant $now,
    ): void {
        $this->state->assertIs(SubmissionState::Submitted);
        $previous = $this->tracking->current();
        $this->tracking = $this->tracking->polled($status, $transfer, $rejection, $now);
        $this->timeline = $this->timeline->touched($now);
        $current = $this->tracking->current();
        if ($current instanceof UrssafStatusChange && $current !== $previous) {
            $this->events[] = new PaymentRequestStatusChanged($this->id, $this->tenantId, $this->externalReference, $previous, $current);
        }
    }

    /**
     * Submitted for less than the maximum polling age (submittedAt + maxPollingAge strictly after now) and its status
     * may still change: no status yet, non-final status, settled (paid, recovered) for less than settledMaxAge, or
     * code unknown to this package version for less than unknownStatusMaxAge (see {@see PollingWindow}).
     */
    public function isPollable(Instant $now, PollingWindow $window): bool
    {
        return $this->state === SubmissionState::Submitted
            && $this->timeline->isSubmittedWithin($now, $window->maxPollingAge())
            && $this->tracking->isStillFollowed($now, $window);
    }

    /**
     * Submitting for at least staleAfter (submission start + staleAfter at or before now): needs reconciliation.
     */
    public function isStaleSubmission(Instant $now, DateInterval $staleAfter): bool
    {
        return $this->state === SubmissionState::Submitting
            && $this->timeline->isSubmissionStartedBefore($now, $staleAfter);
    }

    /**
     * @return list<DomainEvent> the events recorded since the last call, oldest first
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function id(): LocalPaymentRequestId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    public function invoice(): Invoice
    {
        return $this->invoice;
    }

    public function urssafCustomerId(): UrssafCustomerId
    {
        return $this->invoice->customer()->id();
    }

    public function customerBirthDate(): CalendarDate
    {
        return $this->invoice->customer()->birthDate();
    }

    public function billingThirdPartyId(): BillingThirdPartyId
    {
        return $this->invoice->billingThirdPartyId();
    }

    public function invoiceNumber(): InvoiceNumber
    {
        return $this->invoice->number();
    }

    public function invoiceDate(): CalendarDate
    {
        return $this->invoice->date();
    }

    public function employmentPeriod(): EmploymentPeriod
    {
        return $this->invoice->employmentPeriod();
    }

    public function totalInclTax(): Money
    {
        return $this->invoice->totalInclTax();
    }

    public function totalExclTax(): Money
    {
        return $this->invoice->totalExclTax();
    }

    public function deposit(): ?Deposit
    {
        return $this->invoice->deposit();
    }

    public function lines(): ServiceLines
    {
        return $this->invoice->lines();
    }

    public function state(): SubmissionState
    {
        return $this->state;
    }

    /**
     * Number of submission attempts (pending -> submitting) so far.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Start of the last submission attempt; null while never submitted.
     */
    public function lastAttemptAt(): ?Instant
    {
        return $this->timeline->submissionStartedAt();
    }

    /**
     * Errors of the last failed or rejected submission, kept through a retry (empty otherwise).
     *
     * @return list<SubmissionError>
     */
    public function submissionErrors(): array
    {
        return $this->submissionErrors;
    }

    public function tracking(): UrssafTracking
    {
        return $this->tracking;
    }

    public function timeline(): Timeline
    {
        return $this->timeline;
    }

    /**
     * URSSAF id (idDemandePaiement), known once submitted.
     */
    public function paymentRequestId(): ?PaymentRequestId
    {
        return $this->tracking->paymentRequestId();
    }

    /**
     * Current URSSAF status; null while unknown or when the raw code is not a known status.
     */
    public function urssafStatus(): ?PaymentRequestStatus
    {
        return $this->tracking->current()?->status();
    }

    /**
     * Last raw "statut" returned by URSSAF (status code, or error code of a rejected submission).
     */
    public function rawUrssafStatus(): ?string
    {
        return $this->tracking->current()?->rawCode();
    }

    public function lastStatusChangeAt(): ?Instant
    {
        return $this->tracking->current()?->observedAt();
    }

    public function lastPolledAt(): ?Instant
    {
        return $this->tracking->lastPolledAt();
    }

    /**
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition
     */
    private function moveTo(SubmissionState $target, array $errors, Instant $now): void
    {
        $this->state = $this->state->transitionTo($target);
        $this->submissionErrors = $errors;
        $this->timeline = $this->timeline->touched($now);
    }

    private function acknowledge(PaymentRequestId $urssafId, PaymentRequestStatus|string $status, Instant $now): void
    {
        $this->submissionErrors = [];
        $this->tracking = $this->tracking->identifiedAs($urssafId, $status, $now);
        $this->timeline = $this->timeline->submitted($now);
        $this->events[] = new PaymentRequestSubmitted($this->id, $this->tenantId, $this->externalReference, $urssafId, $now);
    }
}
