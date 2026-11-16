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
 * Municipality label, 1 to 50 characters, kept verbatim.
 *
 * - postal address (adressePostale.libelleCommune): letters, digits, hyphens, spaces and apostrophes (e.g. "PARIS 10");
 *   subject "municipality_name";
 * - birth place (communeNaissance.libelleCommune): any printable character except the semicolon;
 *   subject "birth_municipality_name".
 *
 * Violation codes: "<subject>.empty", "<subject>.invalid_characters", "<subject>.too_long".
 */
final readonly class MunicipalityName
{
    public const MAX_LENGTH = 50;
    private const ADDRESS_PATTERN = "/^[\\p{L}\\p{N}' \\-]+$/Du";
    private const BIRTH_PATTERN = '/^[^;\p{Cc}]+$/Du';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue
     */
    public static function ofAddress(string $value): self
    {
        return self::create('municipality_name', self::ADDRESS_PATTERN, $value);
    }

    /**
     * @throws InvalidValue
     */
    public static function ofBirthPlace(string $value): self
    {
        return self::create('birth_municipality_name', self::BIRTH_PATTERN, $value);
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

    private static function create(string $subject, string $pattern, string $value): self
    {
        if (trim($value) === '') {
            throw InvalidValue::empty($subject);
        }
        if (preg_match($pattern, $value) !== 1) {
            throw InvalidValue::because(
                $subject . '.invalid_characters',
                \sprintf('Invalid %s: the value contains characters that are not allowed.', $subject),
            );
        }
        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong($subject, self::MAX_LENGTH);
        }

        return new self($value);
    }
}
