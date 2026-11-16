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
use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingRecheckRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingValidated;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationAnonymised;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationCredentialsRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRetried;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerSubmissionStarted;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerSubmittedToUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Aggregate root: registration of a customer with URSSAF (method 010) and follow-up of the pairing (method 020).
 *
 * Created with {@see self::request()}. The identity (id, tenant, external reference, creation instant) is immutable;
 * the mutable part is the {@see RegistrationStatus} (state machine: {@see RegistrationState}) and the customer, which
 * {@see self::anonymise()} replaces. Every state change records a domain event, see {@see self::releaseEvents()}.
 * Invalid transitions throw {@see InvalidStateTransition} ("customer_registration.invalid_state_transition").
 *
 * Submission attempts are counted (pending -> submitting); {@see self::retry()} refuses to requeue a failed
 * registration once the maximum number of attempts is reached: it then stays in error until handled manually.
 *
 * Not final only because Doctrine generates lazy-loading proxies by extending the mapped entities: final by
 * convention, never extend it. For the same reason the identifier is neither readonly nor private: a proxy is created
 * with its identifier, which Doctrine assigns again (another instance of the same value) when the proxy is loaded. It is
 * never reassigned otherwise.
 *
 * The root exposes its identity, transitions and queries, hence the method count.
 *
 * @final
 *
 * @mago-expect lint:too-many-methods
 */
class CustomerRegistration
{
    /**
     * Default maximum number of submission attempts before {@see self::retry()} refuses to requeue.
     */
    public const DEFAULT_MAX_ATTEMPTS = 5;

    private const AGGREGATE = 'customer_registration';

    /**
     * URSSAF evaluates "not in the future" in French local time.
     */
    private const URSSAF_TIME_ZONE = 'Europe/Paris';

    private RegistrationStatus $status;

    /** @var list<DomainEvent> */
    private array $events = [];

    private function __construct(
        protected RegistrationId $id,
        private readonly TenantId $tenantId,
        private readonly ExternalReference $externalReference,
        private Customer $customer,
        private readonly Instant $createdAt,
    ) {
        $this->status = new RegistrationStatus(RegistrationState::Pending, null, null, [], $createdAt, 0, null);
    }

    /**
     * Requests the registration (state pending) and records {@see CustomerRegistrationRequested}.
     *
     * @throws InvariantViolation "birth_date.in_future" (ERR_DATE_NAISSANCE_FUTUR)
     */
    public static function request(
        RegistrationId $id,
        TenantId $tenantId,
        ExternalReference $externalReference,
        Customer $customer,
        Instant $createdAt,
    ): self {
        if ($customer->civilStatus()->birthDate()->isAfter($createdAt->calendarDate(new DateTimeZone(self::URSSAF_TIME_ZONE)))) {
            throw InvariantViolation::because('birth_date.in_future', 'The birth date must not be in the future.');
        }
        $registration = new self($id, $tenantId, $externalReference, $customer, $createdAt);
        $registration->events[] = new CustomerRegistrationRequested($id, $tenantId, $externalReference, $createdAt);

        return $registration;
    }

    public function id(): RegistrationId
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

    /**
     * The customer as registered, or {@see Customer::anonymised()} once anonymised.
     */
    public function customer(): Customer
    {
        return $this->customer;
    }

    public function createdAt(): Instant
    {
        return $this->createdAt;
    }
    /**
     * pending -> submitting: one more attempt, started now. Concurrent workers should rather use
     * {@see CustomerRegistrationRepository::claimForSubmission()}.
     *
     * @throws InvalidStateTransition
     */
    public function startSubmission(Instant $now): void
    {
        $this->status = $this->status->submissionStarted($now);
        $this->events[] = new CustomerSubmissionStarted($this->id, $this->tenantId, $now);
    }

    /**
     * submitting -> awaiting_pairing (method 010 succeeded). Clears the last errors.
     *
     * @throws InvalidStateTransition
     */
    public function markSubmitted(UrssafCustomerId $urssafCustomerId, Instant $now): void
    {
        $current = $this->status->state();
        if ($current !== RegistrationState::Submitting) {
            throw InvalidStateTransition::between(
                self::AGGREGATE,
                $current->value,
                RegistrationState::AwaitingPairing->value,
            );
        }
        $this->status = $this->status->transitionTo(RegistrationState::AwaitingPairing, $now, $urssafCustomerId, null, []);
        $this->events[] = new CustomerSubmittedToUrssaf($this->id, $this->tenantId, $this->externalReference, $urssafCustomerId, $now);
    }

    /**
     * Applies the method 020 status (only once URSSAF knows the customer):
     * - APPAREILLAGE_VALIDE -> registered, stores the acceptance date (required), records
     *   {@see CustomerPairingValidated} unless already registered with the same acceptance date;
     * - other statuses -> awaiting_pairing, blocked or mandate_expired, records {@see CustomerPairingStatusChanged}
     *   when the state changes.
     * An unchanged status only refreshes the update date (no event).
     *
     * @throws InvalidStateTransition when the customer is not known by URSSAF yet (or any more)
     * @throws InvariantViolation     "customer_registration.pairing_acceptance_required"
     */
    public function applyPairingStatus(PairingStatus $pairingStatus, ?Instant $pairingAcceptedAt, Instant $now): void
    {
        $current = $this->status->state();
        $target = $pairingStatus->registrationState();
        if (!$current->followsPairingStatus()) {
            throw InvalidStateTransition::between(self::AGGREGATE, $current->value, $target->value);
        }
        if (!$pairingStatus->allowsTransmission()) {
            $this->status = $this->status->transitionTo($target, $now, null, null, null);
            if ($target !== $current) {
                $this->events[] = new CustomerPairingStatusChanged($this->id, $this->tenantId, $this->externalReference, $pairingStatus, $current, $target, $now);
            }

            return;
        }
        if (!$pairingAcceptedAt instanceof Instant) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.pairing_acceptance_required',
                'A validated pairing requires the pairing acceptance date.',
            );
        }
        $changed = $current !== RegistrationState::Registered || !$this->status->hasPairingAcceptedAt($pairingAcceptedAt);
        $this->status = $this->status->transitionTo($target, $now, null, $pairingAcceptedAt, null);
        if ($changed) {
            $this->events[] = new CustomerPairingValidated($this->id, $this->tenantId, $this->externalReference, $pairingAcceptedAt, $now);
        }
    }

    /**
     * registered -> awaiting_pairing: URSSAF refused a payment request because the customer / provider link is missing
     * over the period, so the pairing must be confirmed again with method 020 before
     * more requests are sent. Records {@see CustomerPairingRecheckRequested}. Any other state is left unchanged (the
     * pairing is already polled, or the customer is not known by URSSAF).
     */
    public function requestPairingRecheck(Instant $now): void
    {
        if ($this->status->state() !== RegistrationState::Registered) {
            return;
        }
        $this->status = $this->status->transitionTo(RegistrationState::AwaitingPairing, $now, null, null, null);
        $this->events[] = new CustomerPairingRecheckRequested($this->id, $this->tenantId, $now);
    }

    /**
     * Business refusal (e.g. ERR_DGFIP_R2P_INCONNU, ERR_COMPTE_BLOQUE, or the customer is no longer associated):
     * -> rejected (final). Allowed while submitting and while following the pairing status.
     *
     * @param list<RejectionCause> $causes
     *
     * @throws InvalidStateTransition
     */
    public function markRejected(array $causes, Instant $now): void
    {
        $this->status = $this->status->transitionTo(RegistrationState::Rejected, $now, null, null, $causes);
        $this->events[] = new CustomerRegistrationRejected($this->id, $this->tenantId, $this->externalReference, $causes, $now);
    }

    /**
     * Technical failure while submitting (URSSAF unavailable, transport error...): submitting -> error, retryable.
     *
     * @param list<RejectionCause> $causes
     *
     * @throws InvalidStateTransition
     */
    public function markFailed(array $causes, Instant $now): void
    {
        $this->status = $this->status->transitionTo(RegistrationState::Error, $now, null, null, $causes);
        $this->events[] = new CustomerRegistrationFailed($this->id, $this->tenantId, $causes, $now);
    }

    /**
     * URSSAF rejected the tenant credentials while submitting: submitting -> invalid_credentials. Clears the last errors.
     *
     * @throws InvalidStateTransition
     */
    public function markInvalidCredentials(Instant $now): void
    {
        $this->status = $this->status->transitionTo(RegistrationState::InvalidCredentials, $now, null, null, []);
        $this->events[] = new CustomerRegistrationCredentialsRejected($this->id, $this->tenantId, $now);
    }

    /**
     * error | invalid_credentials -> pending. The last errors are kept until the next attempt completes.
     *
     * A technical failure (error) is requeued only while fewer than maxAttempts attempts were made (see
     * {@see self::canRetry()}); refused credentials are always requeued (the credentials, not the registration, were
     * at fault).
     *
     * @param positive-int $maxAttempts
     *
     * @throws InvalidStateTransition
     * @throws InvariantViolation     "customer_registration.max_attempts_reached"
     */
    public function retry(Instant $now, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): void
    {
        if (!$this->canRetry($maxAttempts)) {
            throw InvariantViolation::because(
                self::AGGREGATE . '.max_attempts_reached',
                \sprintf('The registration failed %d times: it is not retried automatically any more.', $this->status->attempts()),
                ['attempts' => $this->status->attempts(), 'max_attempts' => $maxAttempts],
            );
        }
        $this->status = $this->status->transitionTo(RegistrationState::Pending, $now, null, null, null);
        $this->events[] = new CustomerRegistrationRetried($this->id, $this->tenantId, $now);
    }

    /**
     * False for a registration in error once maxAttempts submission attempts were made; true otherwise (the
     * transition itself is checked by {@see self::retry()}).
     *
     * @param positive-int $maxAttempts
     */
    public function canRetry(int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): bool
    {
        return $this->status->state() !== RegistrationState::Error || $this->status->hasAttemptsLeft($maxAttempts);
    }

    /**
     * Submitting for at least staleAfter (start of the last attempt + staleAfter at or before now): the process
     * that submitted it probably crashed and the outcome of method 010 is unknown.
     */
    public function isStaleSubmission(Instant $now, DateInterval $staleAfter): bool
    {
        return $this->status->state() === RegistrationState::Submitting
            && $this->status->isLastAttemptStartedBefore($now, $staleAfter);
    }

    /**
     * GDPR erasure of a registration in a final state (rejected): replaces the customer by
     * {@see Customer::anonymised()} and records {@see CustomerRegistrationAnonymised}. The identity, the status and
     * the URSSAF customer id are kept. Anonymising again changes nothing.
     *
     * @throws InvalidStateTransition "customer_registration.invalid_state_transition" when the state is not final
     */
    public function anonymise(Instant $now): void
    {
        $current = $this->status->state();
        if (!$current->isFinal()) {
            throw InvalidStateTransition::between(self::AGGREGATE, $current->value, 'anonymised');
        }
        $anonymised = Customer::anonymised();
        if ($this->customer->equals($anonymised)) {
            return;
        }
        $this->customer = $anonymised;
        $this->status = $this->status->touched($now);
        $this->events[] = new CustomerRegistrationAnonymised($this->id, $this->tenantId, $now);
    }

    /**
     * Current state, URSSAF id, pairing acceptance, last errors, and the payment request eligibility checks.
     */
    public function status(): RegistrationStatus
    {
        return $this->status;
    }

    /**
     * Returns the events recorded since the last call, and forgets them.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }
}
