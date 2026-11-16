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

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Free-text part of a postal address, kept verbatim:
 * - street name (libelleVoie), max 28 characters, subject "street_name";
 * - complement (complement), max 38 characters, subject "address_complement";
 * - locality (lieuDit), max 38 characters, subject "locality".
 *
 * Violation codes: "<subject>.empty" (blank), "<subject>.invalid_characters" (control characters or invalid UTF-8),
 * "<subject>.too_long".
 */
final readonly class AddressText
{
    public const STREET_NAME_MAX_LENGTH = 28;
    public const COMPLEMENT_MAX_LENGTH = 38;
    public const LOCALITY_MAX_LENGTH = 38;
    private const PATTERN = '/^\P{Cc}+$/Du';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue
     */
    public static function streetName(string $value): self
    {
        return self::create('street_name', self::STREET_NAME_MAX_LENGTH, $value);
    }

    /**
     * @throws InvalidValue
     */
    public static function complement(string $value): self
    {
        return self::create('address_complement', self::COMPLEMENT_MAX_LENGTH, $value);
    }

    /**
     * @throws InvalidValue
     */
    public static function locality(string $value): self
    {
        return self::create('locality', self::LOCALITY_MAX_LENGTH, $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Personal data: masked in debug output (var_dump, print_r, dumpers). There is deliberately no __toString().
     *
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    private static function create(string $subject, int $maxLength, string $value): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty($subject);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::because(
                $subject . '.invalid_characters',
                \sprintf('Invalid %s: control characters are not allowed.', $subject),
            );
        }
        if (mb_strlen($value) > $maxLength) {
            throw InvalidValue::tooLong($subject, $maxLength);
        }

        return new self($value);
    }
}
