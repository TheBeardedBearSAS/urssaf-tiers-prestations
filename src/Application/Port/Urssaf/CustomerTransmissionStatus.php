<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Result of method 020: can payment requests be transmitted for this customer?
 *
 * The status code is kept raw so that unknown future codes are preserved; {@see self::pairingStatus()} gives its
 * meaning (null for a code unknown to this package version). When transmission is allowed, the adapter parses the
 * acceptance date found in the description ("JJ/MM/AAAA HH:mi:ss", French local time by default) into
 * {@see self::pairingAcceptedAt()}.
 */
final readonly class CustomerTransmissionStatus
{
    private function __construct(
        private UrssafCustomerId $customerId,
        private string $code,
        private bool $transmissionAllowed,
        private string $description,
        private ?Instant $pairingAcceptedAt,
    ) {}

    /**
     * etat = "OK": payment requests may be sent for employment periods starting at or after $pairingAcceptedAt.
     */
    public static function allowed(
        UrssafCustomerId $customerId,
        string $code,
        string $description,
        Instant $pairingAcceptedAt,
    ): self {
        return new self($customerId, $code, true, $description, $pairingAcceptedAt);
    }

    /**
     * etat = "KO": no payment request can be sent yet (or anymore).
     *
     * @throws InvariantViolation when the code is empty
     */
    public static function blocked(UrssafCustomerId $customerId, string $code, string $description): self
    {
        if ($code === '') {
            throw InvariantViolation::because(
                'customer_transmission_status.empty_code',
                'A blocked transmission status must carry a status code.',
            );
        }

        return new self($customerId, $code, false, $description, null);
    }

    public function customerId(): UrssafCustomerId
    {
        return $this->customerId;
    }

    public function code(): string
    {
        return $this->code;
    }

    /**
     * Meaning of the status code; null when the code is unknown to this package version.
     */
    public function pairingStatus(): ?PairingStatus
    {
        return PairingStatus::fromCode($this->code);
    }

    public function isTransmissionAllowed(): bool
    {
        return $this->transmissionAllowed;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * Null unless transmission is allowed.
     */
    public function pairingAcceptedAt(): ?Instant
    {
        return $this->pairingAcceptedAt;
    }
}
