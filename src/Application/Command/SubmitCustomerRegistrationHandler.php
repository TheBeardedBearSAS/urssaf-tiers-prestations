<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerRegistrar;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UrssafErrorTranslator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use Throwable;

/**
 * Claims a pending registration (pending -> submitting, a single worker wins) and calls method 010:
 * - success -> awaiting_pairing ({@see ItemOutcome::Accepted});
 * - customer already registered with this provider (its URSSAF id is not returned) -> error with the URSSAF errors,
 *   never retried automatically ({@see ItemOutcome::ManualReview});
 * - other HTTP 400 (validation or business errors alike) -> rejected, final ({@see ItemOutcome::Rejected});
 * - credentials refused -> invalid_credentials ({@see ItemOutcome::InvalidCredentials}), see RequeueAfterInvalidCredentials;
 * - temporary failure known to have happened before URSSAF processed the call (HTTP 429, token request, local rate
 *   limit) -> error then pending again, and {@see RetryableUrssafFailure} is thrown so that the message is retried;
 *   left in error instead once the policy maximum number of attempts is reached;
 * - failure after which URSSAF may have registered the customer (transport error or timeout, HTTP 5xx) -> error with
 *   the cause {@see self::OUTCOME_UNKNOWN}, never retried automatically ({@see ItemOutcome::ManualReview}): sending it
 *   again would be refused as already registered and the URSSAF customer id would be lost;
 * - any other failure (unreadable answer, unexpected exception) -> the same, and the failure is rethrown unchanged.
 * Not claimed (unknown id, not pending any more) -> {@see ItemOutcome::NotClaimed}, nothing else happens.
 *
 * A registration left for manual review must be checked by an operator (e.g. in the URSSAF provider space): once sure
 * that URSSAF did not register the customer, retry it (CustomerRegistration::retry()).
 */
final readonly class SubmitCustomerRegistrationHandler
{
    /**
     * Cause recorded when URSSAF may have processed the registration although no usable answer was received.
     */
    public const OUTCOME_UNKNOWN = 'urssaf.submission_outcome_unknown';

    /**
     * Cause recorded when URSSAF was temporarily unavailable before processing the registration.
     */
    public const UNAVAILABLE = 'urssaf.unavailable';

    public function __construct(
        private CustomerRegistrationRepository $registrations,
        private CustomerRegistrar $registrar,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws Throwable {@see RetryableUrssafFailure} after a temporary failure, the unexpected failure otherwise
     */
    public function __invoke(SubmitCustomerRegistration $command): ItemOutcome
    {
        if (!$this->registrations->claimForSubmission($command->registrationId, $this->now())) {
            return ItemOutcome::NotClaimed;
        }
        $registration = $this->registrations->get($command->registrationId);

        try {
            $urssafCustomerId = $this->registrar->register($registration->tenantId(), $registration);
        } catch (UrssafRequestRejected $rejection) {
            return $this->refused($registration, $rejection);
        } catch (UrssafAuthenticationFailed) {
            return $this->update($registration, ItemOutcome::InvalidCredentials, static function (CustomerRegistration $registration, Instant $now): void {
                $registration->markInvalidCredentials($now);
            });
        } catch (RetryableException $failure) {
            return $this->unavailable($registration, $failure);
        } catch (Throwable $failure) {
            $this->leaveForReview($registration, [RejectionCause::of(self::OUTCOME_UNKNOWN, $failure::class)]);

            throw $failure;
        }

        return $this->update($registration, ItemOutcome::Accepted, static function (CustomerRegistration $registration, Instant $now) use ($urssafCustomerId): void {
            $registration->markSubmitted($urssafCustomerId, $now);
        });
    }

    private function refused(CustomerRegistration $registration, UrssafRequestRejected $rejection): ItemOutcome
    {
        $causes = UrssafErrorTranslator::rejectionCauses($rejection->errors());
        if ($rejection->isAlreadyRegistered()) {
            return $this->leaveForReview($registration, $causes);
        }

        return $this->update($registration, ItemOutcome::Rejected, static function (CustomerRegistration $registration, Instant $now) use ($causes): void {
            $registration->markRejected($causes, $now);
        });
    }

    /**
     * @throws RetryableUrssafFailure when the call is known not to have been processed (requeued)
     */
    private function unavailable(CustomerRegistration $registration, RetryableException $failure): ItemOutcome
    {
        if ($failure instanceof UrssafUnavailable && $failure->mayHaveReachedUrssaf()) {
            return $this->leaveForReview($registration, [RejectionCause::of(self::OUTCOME_UNKNOWN, $failure->getMessage())]);
        }
        $this->requeue($registration, RejectionCause::of(self::UNAVAILABLE, $failure->getMessage()));

        throw RetryableUrssafFailure::causedBy($failure);
    }

    private function requeue(CustomerRegistration $registration, RejectionCause $cause): void
    {
        $maxAttempts = $this->policy->maxSubmissionAttempts();
        $this->update($registration, ItemOutcome::Requeued, static function (CustomerRegistration $registration, Instant $now) use ($cause, $maxAttempts): void {
            $registration->markFailed([$cause], $now);
            if ($registration->canRetry($maxAttempts)) {
                $registration->retry($now, $maxAttempts);
            }
        });
    }

    /**
     * Error with the causes, without requeue.
     *
     * @param list<RejectionCause> $causes
     */
    private function leaveForReview(CustomerRegistration $registration, array $causes): ItemOutcome
    {
        return $this->update($registration, ItemOutcome::ManualReview, static function (CustomerRegistration $registration, Instant $now) use ($causes): void {
            $registration->markFailed($causes, $now);
        });
    }

    /**
     * @param callable(CustomerRegistration, Instant): void $change
     * @param-immediately-invoked-callable $change
     */
    private function update(CustomerRegistration $registration, ItemOutcome $outcome, callable $change): ItemOutcome
    {
        return $this->unitOfWork->run(function (RecordedEvents $events) use ($registration, $outcome, $change): ItemOutcome {
            $change($registration, $this->now());
            $this->registrations->save($registration);
            $events->record(...$registration->releaseEvents());

            return $outcome;
        });
    }

    private function now(): Instant
    {
        return Instant::fromDateTime($this->clock->now());
    }
}
