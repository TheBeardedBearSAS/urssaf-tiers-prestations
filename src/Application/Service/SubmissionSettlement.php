<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Applies what happened to a batch of submitting payment requests (method 050), each call in one transaction: the
 * requests (and the customer registrations they move) are saved and their events published once committed.
 */
final readonly class SubmissionSettlement
{
    /**
     * @param positive-int $maxAttempts
     */
    public function __construct(
        private PaymentRequestRepository $paymentRequests,
        private CustomerRegistrationRepository $registrations,
        private UnitOfWork $unitOfWork,
        private Clock $clock,
        private int $maxAttempts,
    ) {}

    /**
     * The outcomes returned by URSSAF, matched to the requests ({@see SubmissionOutcomeApplier}).
     *
     * @param list<PaymentRequest>    $batch
     * @param list<SubmissionOutcome> $outcomes
     *
     * @return list<BatchItem>
     */
    public function outcomes(array $batch, array $outcomes): array
    {
        $matched = SubmissionOutcomeApplier::match($batch, $outcomes);
        $maxAttempts = $this->maxAttempts;
        $registrations = $this->registrations;

        return $this->settle($batch, static function (PaymentRequest $request, Instant $now, int $position, RecordedEvents $events) use ($matched, $maxAttempts, $registrations): BatchItem {
            $outcome = $matched[$position] ?? null;
            $item = SubmissionOutcomeApplier::apply($request, $outcome, $now, $maxAttempts);
            RegistrationFollowUp::afterRejection($registrations, $request, $outcome?->rejectionKind(), $now, $events);

            return $item;
        });
    }

    /**
     * Every request rejected with the errors of a refused call (or with $fallback when it carries none).
     *
     * @param list<PaymentRequest> $requests
     *
     * @return list<BatchItem>
     */
    public function rejected(array $requests, ApiErrors $errors, SubmissionError $fallback): array
    {
        $submissionErrors = $errors->isEmpty() ? [$fallback] : UrssafErrorTranslator::submissionErrors($errors);

        return $this->settle(
            $requests,
            static fn(PaymentRequest $request, Instant $now): BatchItem => SubmissionOutcomeApplier::reject($request, $submissionErrors, '', $now),
        );
    }

    /**
     * Every request failed with the cause, then pending again unless the maximum number of attempts is reached.
     *
     * @param list<PaymentRequest> $requests
     *
     * @return list<BatchItem>
     */
    public function requeued(array $requests, SubmissionError $cause): array
    {
        $maxAttempts = $this->maxAttempts;

        return $this->settle(
            $requests,
            static fn(PaymentRequest $request, Instant $now): BatchItem => SubmissionOutcomeApplier::requeue($request, [$cause], $now, $maxAttempts),
        );
    }

    /**
     * @param list<PaymentRequest> $requests
     *
     * @return list<BatchItem>
     */
    public function invalidCredentials(array $requests): array
    {
        return $this->settle($requests, static function (PaymentRequest $request, Instant $now): BatchItem {
            $request->markInvalidCredentials($now);

            return new BatchItem($request->id()->value(), ItemOutcome::InvalidCredentials);
        });
    }

    /**
     * Applies the change to every request (with its position), saves them and records their events, in one
     * transaction (none for no request).
     *
     * @param list<PaymentRequest>                                              $requests
     * @param callable(PaymentRequest, Instant, int, RecordedEvents): BatchItem $change
     * @param-immediately-invoked-callable $change
     *
     * @return list<BatchItem>
     */
    private function settle(array $requests, callable $change): array
    {
        if ($requests === []) {
            return [];
        }

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($requests, $change): array {
            $now = Instant::fromDateTime($this->clock->now());
            $items = [];
            foreach ($requests as $position => $request) {
                $items[] = $change($request, $now, $position, $events);
                $this->paymentRequests->save($request);
                $events->record(...$request->releaseEvents());
            }

            return $items;
        });
    }
}
