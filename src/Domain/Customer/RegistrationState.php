<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer;

/**
 * Lifecycle of a customer registration.
 *
 * pending -> submitting -> awaiting_pairing -> registered (APPAREILLAGE_VALIDE)
 *                       -> rejected (business refusal, final)
 *                       -> error | invalid_credentials -> pending (retry)
 * awaiting_pairing, registered, blocked and mandate_expired follow the method 020 status (each one may move to any
 * other, or to itself when the status is confirmed) and may become rejected (customer no longer associated).
 */
enum RegistrationState: string
{
    case Pending = 'pending';
    case Submitting = 'submitting';
    case AwaitingPairing = 'awaiting_pairing';
    case Registered = 'registered';
    case Rejected = 'rejected';
    case Blocked = 'blocked';
    case MandateExpired = 'mandate_expired';
    case Error = 'error';
    case InvalidCredentials = 'invalid_credentials';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Submitting],
            self::Submitting => [self::AwaitingPairing, self::Rejected, self::Error, self::InvalidCredentials],
            self::AwaitingPairing, self::Registered, self::Blocked, self::MandateExpired => [
                self::AwaitingPairing,
                self::Registered,
                self::Blocked,
                self::MandateExpired,
                self::Rejected,
            ],
            self::Rejected => [],
            self::Error, self::InvalidCredentials => [self::Pending],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * True for the states driven by the method 020 status (the customer is known by URSSAF).
     */
    public function followsPairingStatus(): bool
    {
        return match ($this) {
            self::AwaitingPairing, self::Registered, self::Blocked, self::MandateExpired => true,
            self::Pending, self::Submitting, self::Rejected, self::Error, self::InvalidCredentials => false,
        };
    }

    /**
     * True for the states whose pairing status must still be polled with method 020 (awaiting_pairing, blocked,
     * mandate_expired): the customer is known by URSSAF but cannot receive payment requests yet, or any more.
     */
    public function hasPairingToFollow(): bool
    {
        return $this->followsPairingStatus() && $this !== self::Registered;
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
