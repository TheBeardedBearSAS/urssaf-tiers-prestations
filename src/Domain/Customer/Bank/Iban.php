<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * International bank account number (iban): 15 to 34 characters matching the official pattern, with a valid
 * ISO 13616 (mod 97) check digit. Normalised: spaces removed, upper case.
 */
final readonly class Iban
{
    private const SUBJECT = 'iban';
    private const PATTERN = '/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/D';
    private const DIGIT_OFFSET = 48;
    private const LETTER_OFFSET = 55;
    private const BASE = 10;
    private const MODULUS = 97;

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "iban.invalid_format" or "iban.invalid_checksum"
     */
    public static function fromString(string $value): self
    {
        $normalised = strtoupper(str_replace(' ', '', $value));
        if (preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '15 to 34 characters: country code, check digits, account');
        }
        if (!self::hasValidChecksum($normalised)) {
            throw InvalidValue::because(
                self::SUBJECT . '.invalid_checksum',
                'Invalid iban: the check digits do not match (ISO 13616).',
            );
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

    /**
     * Personal data: masked in debug output (var_dump, print_r, dumpers). There is deliberately no __toString().
     *
     * @return array{value: string}
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    private static function hasValidChecksum(string $iban): bool
    {
        $remainder = 0;
        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $character) {
            $value = \ord($character) - (ctype_digit($character) ? self::DIGIT_OFFSET : self::LETTER_OFFSET);
            $remainder = ($remainder * ($value < self::BASE ? self::BASE : self::BASE * self::BASE) + $value) % self::MODULUS;
        }

        return $remainder === 1;
    }
}
