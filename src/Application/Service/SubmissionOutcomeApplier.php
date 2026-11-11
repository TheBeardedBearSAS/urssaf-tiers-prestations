<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

/**
 * Applies the method 050 outcome of one submitting payment request:
 * - no outcome -> left submitting, to be reconciled ({@see ItemOutcome::Unconfirmed});
 * - accepted (URSSAF id returned) -> submitted, the warnings returned alongside are reported with the status;
 * - duplicate invoice number -> left submitting: an earlier attempt may have been integrated, it is reconciled by
 *   searching its invoice number ({@see ItemOutcome::Unconfirmed}, codes attached);
 * - technical error -> error then pending again ({@see ItemOutcome::Requeued}), or left in error once the maximum
 *   number of attempts is reached ({@see ItemOutcome::Exhausted});
 * - any other rejection -> rejected, final ({@see ItemOutcome::Rejected}).
 */
final class SubmissionOutcomeApplier
{
    /**
     * The outcome of each request (same positions), matched on the invoice number echoed by URSSAF; when an outcome
     * does not echo it, the outcome at the same position is used, provided URSSAF answered one outcome per request.
     * Null when no outcome (or more than one) matches.
     *
     * @param list<PaymentRequest>    $requests
     * @param list<SubmissionOutcome> $outcomes
     *
     * @return list<SubmissionOutcome|null>
     */
    public static function match(array $requests, array $outcomes): array
    {
        $positional = \count($outcomes) === \count($requests);
        $matched = [];
        foreach ($requests as $position => $request) {
            $matching = array_values(array_filter(
                $outcomes,
                static fn(SubmissionOutcome $outcome): bool => $outcome->concerns($request->invoiceNumber()),
            ));
            $atPosition = $positional ? $outcomes[$position] ?? null : null;
            $matched[] = match (\count($matching)) {
                0 => $atPosition?->invoiceNumber() === '' ? $atPosition : null,
                1 => $matching[0],
                default => null,
            };
        }

        return $matched;
    }

    /**
     * @param positive-int $maxAttempts
     *
     * @throws InvalidStateTransition when the request is not submitting
     */
    public static function apply(PaymentRequest $request, ?SubmissionOutcome $outcome, Instant $now, int $maxAttempts): BatchItem
    {
        $subject = $request->id()->value();
        if (!$outcome instanceof SubmissionOutcome) {
            return new BatchItem($subject, ItemOutcome::Unconfirmed);
        }
        $codes = $outcome->errors()->codes();
        // Only an accepted outcome carries the URSSAF id.
        $urssafId = $outcome->paymentRequestId();
        if ($urssafId instanceof PaymentRequestId) {
            $request->markAccepted($urssafId, $outcome->rawStatus(), $now);

            return new BatchItem($subject, ItemOutcome::Accepted, [$outcome->rawStatus(), ...$codes]);
        }
        $errors = UrssafErrorTranslator::submissionErrors($outcome->errors());

        return match ($outcome->rejectionKind()) {
            RejectionKind::Duplicate => new BatchItem($subject, ItemOutcome::Unconfirmed, $codes),
            RejectionKind::Retryable => self::requeue($request, $errors, $now, $maxAttempts),
            default => self::reject($request, $errors, $outcome->rawStatus(), $now),
        };
    }

    /**
     * Error (with the errors), then pending again unless the maximum number of attempts is reached.
     *
     * @param list<SubmissionError> $errors
     * @param positive-int          $maxAttempts
     *
     * @throws InvalidStateTransition when the request is not submitting
     */
    public static function requeue(PaymentRequest $request, array $errors, Instant $now, int $maxAttempts): BatchItem
    {
        $request->markFailed($errors, $now);
        $codes = self::codes($errors);
        if (!$request->canRetry($maxAttempts)) {
            return new BatchItem($request->id()->value(), ItemOutcome::Exhausted, $codes);
        }
        $request->retry($now, $maxAttempts);

        return new BatchItem($request->id()->value(), ItemOutcome::Requeued, $codes);
    }

    /**
     * @param list<SubmissionError> $errors
     *
     * @throws InvalidStateTransition when the request is not submitting
     */
    public static function reject(PaymentRequest $request, array $errors, string $rawStatus, Instant $now): BatchItem
    {
        $request->markRejected($errors, $rawStatus, $now);

        return new BatchItem($request->id()->value(), ItemOutcome::Rejected, self::codes($errors));
    }

    /**
     * @param list<SubmissionError> $errors
     *
     * @return list<string>
     */
    private static function codes(array $errors): array
    {
        return array_map(static fn(SubmissionError $error): string => $error->code(), $errors);
    }
}
