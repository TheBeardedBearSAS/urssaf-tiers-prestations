<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer;

/**
 * Payment request transmission status returned by method 020 (statut.code). Backed by the URSSAF codes.
 */
enum PairingStatus: string
{
    /** Pairing accepted: payment requests may be sent for periods starting after the acceptance date. */
    case Validated = 'APPAREILLAGE_VALIDE';

    /** Pairing being processed. */
    case InProgress = 'APPAREILLAGE_EC';

    /** Pairing not finalised: the customer must accept it (and the terms of use) in their online account. */
    case NotFinalised = 'APPAREILLAGE_NF';

    /** Customer account blocked. */
    case CustomerBlocked = 'PARTICULIER_BLOQUE';

    /** Direct debit mandate expired or expiring. */
    case MandateExpired = 'MANDAT_ECHU';

    /**
     * Null for codes unknown to this version of the package.
     */
    public static function fromCode(string $code): ?self
    {
        return self::tryFrom($code);
    }

    public function allowsTransmission(): bool
    {
        return $this === self::Validated;
    }

    /**
     * Registration state implied by this status.
     */
    public function registrationState(): RegistrationState
    {
        return match ($this) {
            self::Validated => RegistrationState::Registered,
            self::InProgress, self::NotFinalised => RegistrationState::AwaitingPairing,
            self::CustomerBlocked => RegistrationState::Blocked,
            self::MandateExpired => RegistrationState::MandateExpired,
        };
    }
}
