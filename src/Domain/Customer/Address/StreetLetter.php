<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Street number suffix letter (lettreVoie): a single letter "A" to "Z" ("B" = bis, "T" = ter, "Q" = quater,
 * "C" = quinquies). Normalised to upper case.
 *
 * A value object rather than an enum: 26 cases exceed the project's enum size limit.
 */
final readonly class StreetLetter implements Stringable
{
    private const SUBJECT = 'street_letter';
    private const PATTERN = '/^[A-Z]$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "street_letter.invalid_format"
     */
    public static function fromString(string $value): self
    {
        $normalised = strtoupper($value);
        if (preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'a single letter from A to Z');
        }

        return new self($normalised);
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
