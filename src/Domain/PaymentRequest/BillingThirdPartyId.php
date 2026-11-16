<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Billing third party identifier (idTiersFacturation). Required by the contract but not used by URSSAF: any value is
 * accepted (official example: "menage.fr"); the default value is a host configuration concern. The 50-character
 * bound is a package choice (the specification gives none).
 */
final readonly class BillingThirdPartyId implements Stringable
{
    private const SUBJECT = 'billing_third_party_id';
    public const MAX_LENGTH = 50;

    private function __construct(private string $value) {}

    /**
     * Rejects blank values and values longer than {@see self::MAX_LENGTH} characters. The value is kept verbatim.
     *
     * @throws InvalidValue
     */
    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong(self::SUBJECT, self::MAX_LENGTH);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
