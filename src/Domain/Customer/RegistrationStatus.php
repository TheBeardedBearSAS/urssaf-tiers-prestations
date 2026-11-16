<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer;

use DateInterval;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Current status of a {@see CustomerRegistration}: state, URSSAF customer id, pairing acceptance date, causes of the
 * last refusal / failure, last update, and the submission attempts (count and start of the last one). Immutable.
 *
 * Invariants: the states following the pairing status require the URSSAF customer id; "registered" requires the
 * pairing acceptance date; the attempt count is not negative.

 */
final readonly class RegistrationStatus
{
    private const AGGREGATE = 'customer_registration';

    /**
     * @param list<RejectionCause> $lastErrors
     * @param int                  $attempts      number of submission attempts (pending -> submitting) so far
     * @param Instant|null         $lastAttemptAt start of the last submission attempt
     *
     * @throws InvariantViolation "customer_registration.urssaf_customer_id_required",
     *                            "customer_registration.pairing_acceptance_required" or
     *                            "customer_registration.negative_attempts"
     */
    public function __construct(
        private RegistrationState $state,
        private ?UrssafCustomerId $urssafCustomerId,
        private ?Instant $pairingAcceptedAt,
        private array $lastErrors,
        private Instant $updatedAt,
        private int $attempts,
        private ?Instant $lastAttemptAt,
    ) {
        if (!$urssafCustomerId instanceof UrssafCustomerId && $state->followsPairingStatus()) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.urssaf_customer_id_required',
                \sprintf('A customer registration in state "%s" requires the URSSAF customer id.', $state->value),
            );
        }
        if (!$pairingAcceptedAt instanceof Instant && $state === RegistrationState::Registered) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.pairing_acceptance_required',
                'A registered customer requires the pairing acceptance date.',
            );
        }
        if ($attempts < 0) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.negative_attempts',
                'The number of submission attempts must not be negative.',
                ['attempts' => $attempts],
            );
        }
    }

    /**
     * Moves to the target state if the transition is allowed; a null argument keeps the current value.
     *
     * @param list<RejectionCause>|null $lastErrors
     *
     * @throws InvalidStateTransition "customer_registration.invalid_state_transition"
     * @throws InvariantViolation
     */
    public function transitionTo(
        RegistrationState $target,
        Instant $now,
        ?UrssafCustomerId $urssafCustomerId,
        ?Instant $pairingAcceptedAt,
        ?array $lastErrors,
    ): self {
        if (!$this->state->canTransitionTo($target)) {
            throw InvalidStateTransition::between(self::AGGREGATE, $this->state->value, $target->value);
        }

        return new self(
            $target,
            $urssafCustomerId ?? $this->urssafCustomerId,
            $pairingAcceptedAt ?? $this->pairingAcceptedAt,
            $lastErrors ?? $this->lastErrors,
            $now,
            $this->attempts,
            $this->lastAttemptAt,
        );
    }

    /**
     * pending -> submitting: one more attempt, started now.
     *
     * @throws InvalidStateTransition
     */
    public function submissionStarted(Instant $now): self
    {
        $submitting = $this->transitionTo(RegistrationState::Submitting, $now, null, null, null);

        return new self(
            $submitting->state,
            $submitting->urssafCustomerId,
            $submitting->pairingAcceptedAt,
            $submitting->lastErrors,
            $now,
            $this->attempts + 1,
            $now,
        );
    }

    /**
     * Same status, updated now.
     */
    public function touched(Instant $now): self
    {
        return new self(
            $this->state,
            $this->urssafCustomerId,
            $this->pairingAcceptedAt,
            $this->lastErrors,
            $now,
            $this->attempts,
            $this->lastAttemptAt,
        );
    }

    public function state(): RegistrationState
    {
        return $this->state;
    }

    public function urssafCustomerId(): ?UrssafCustomerId
    {
        return $this->urssafCustomerId;
    }

    /**
     * Last pairing acceptance date reported by URSSAF (kept when the pairing is later suspended).
     */
    public function pairingAcceptedAt(): ?Instant
    {
        return $this->pairingAcceptedAt;
    }

    public function hasPairingAcceptedAt(Instant $acceptedAt): bool
    {
        return $this->pairingAcceptedAt?->equals($acceptedAt) ?? false;
    }

    /**
     * Causes of the last refusal or failure; cleared on successful submission and on invalid credentials, kept
     * through a retry.
     *
     * @return list<RejectionCause>
     */
    public function lastErrors(): array
    {
        return $this->lastErrors;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
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
        return $this->lastAttemptAt;
    }

    /**
     * True while fewer than maxAttempts submission attempts were made.
     */
    public function hasAttemptsLeft(int $maxAttempts): bool
    {
        return $this->attempts < $maxAttempts;
    }

    /**
     * True when the last submission attempt started at or before now - age (false when never started).
     */
    public function isLastAttemptStartedBefore(Instant $now, DateInterval $age): bool
    {
        return $this->lastAttemptAt instanceof Instant
            && !Instant::fromDateTime($this->lastAttemptAt->toDateTimeImmutable()->add($age))->isAfter($now);
    }

    public function canReceivePaymentRequests(): bool
    {
        return $this->state === RegistrationState::Registered;
    }

    /**
     * @throws InvariantViolation "customer_registration.not_registered"
     */
    public function ensureCanReceivePaymentRequests(): void
    {
        if (!$this->canReceivePaymentRequests()) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.not_registered',
                \sprintf(
                    'Payment requests can only be sent to a registered customer (current state: "%s").',
                    $this->state->value,
                ),
                ['state' => $this->state->value],
            );
        }
    }

    /**
     * URSSAF refuses employment periods starting before the pairing acceptance (ERR_LIEN_PARTICULIER_PRESTATAIRE).
     *
     * @throws InvariantViolation "customer_registration.not_registered" or "employment_period.before_pairing"
     */
    public function assertEmploymentPeriodAllowed(EmploymentPeriod $period): void
    {
        $this->ensureCanReceivePaymentRequests();
        if ($this->pairingAcceptedAt instanceof Instant && $period->startsBefore($this->pairingAcceptedAt)) {
            $acceptedAt = $this->pairingAcceptedAt->toIso8601();

            throw InvariantViolation::because(
                'employment_period.before_pairing',
                \sprintf('The employment period must not start before the pairing acceptance (%s).', $acceptedAt),
                ['pairing_accepted_at' => $acceptedAt],
            );
        }
    }
}
