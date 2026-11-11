<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;

/**
 * Local lifecycle of the transmission of a payment request to URSSAF (method 050).
 *
 * Transitions:
 * - pending             -> submitting
 * - submitting          -> submitted | rejected | error | invalid_credentials
 * - error               -> pending (retry)
 * - invalid_credentials -> pending (retry once the credentials are fixed)
 * - submitted, rejected: terminal
 *
 * Reconciliation (see {@see self::reconciledAsSubmitted()}) additionally moves error to submitted, when a search by
 * invoice number proves that a submission which failed technically was in fact integrated.
 */
enum SubmissionState: string
{
    private const AGGREGATE = 'payment_request';

    /** Validated locally, waiting to be sent. */
    case Pending = 'pending';

    /** Being sent (claimed by a worker); the outcome is unknown until URSSAF answers. */
    case Submitting = 'submitting';

    /** Integrated by URSSAF: the request has an URSSAF id and its status is followed. */
    case Submitted = 'submitted';

    /** Business errors returned by URSSAF: the request must be fixed and created again. */
    case Rejected = 'rejected';

    /** Technical failure (network, 5xx, quota...): may be retried. */
    case Error = 'error';

    /** URSSAF refused the tenant credentials: retry once they are fixed. */
    case InvalidCredentials = 'invalid_credentials';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Submitting],
            self::Submitting => [self::Submitted, self::Rejected, self::Error, self::InvalidCredentials],
            self::Error, self::InvalidCredentials => [self::Pending],
            self::Submitted, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @throws InvalidStateTransition when the transition is not allowed
     */
    public function transitionTo(self $target): self
    {
        if (!$this->canTransitionTo($target)) {
            throw InvalidStateTransition::between(self::AGGREGATE, $this->value, $target->value);
        }

        return $target;
    }

    /**
     * Reconciliation path: submitting (stale) or error -> submitted.
     *
     * @throws InvalidStateTransition from any other state
     */
    public function reconciledAsSubmitted(): self
    {
        return $this === self::Error ? self::Submitted : $this->transitionTo(self::Submitted);
    }

    /**
     * @throws InvalidStateTransition when the state is not the expected one
     */
    public function assertIs(self $expected): void
    {
        if ($this !== $expected) {
            throw InvalidStateTransition::between(self::AGGREGATE, $this->value, $expected->value);
        }
    }
}
