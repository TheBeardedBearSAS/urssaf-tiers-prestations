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

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\DepartmentCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * INSEE municipality code (codeCommune of the postal address): 5 characters, e.g. "69101", "2B072", "97411".
 * Normalised to upper case.
 *
 * It also derives the 3-character birth department and birth municipality codes expected by the birth place
 * (lieuNaissance): metropolitan "69101" gives "069" / "101", Corsica "2B072" gives "02B" / "072", overseas
 * "97411" gives "974" / "011".
 */
final readonly class MunicipalityCode
{
    private const SUBJECT = 'municipality_code';
    private const PATTERN = '/^[0-9][0-9AB][0-9]{3}$/D';
    private const OVERSEAS_PREFIX = '97';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "municipality_code.invalid_format"
     */
    public static function fromString(string $value): self
    {
        $normalised = strtoupper($value);
        if (preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '5-character INSEE code such as 69101 or 2B072');
        }

        return new self($normalised);
    }

    /**
     * Overseas ("97x"): the first 3 characters; otherwise "0" followed by the first 2 characters.
     */
    public function birthDepartmentCode(): DepartmentCode
    {
        return DepartmentCode::fromString(
            $this->isOverseas() ? substr($this->value, 0, 3) : '0' . substr($this->value, 0, 2),
        );
    }

    /**
     * Overseas ("97x"): "0" followed by the last 2 characters; otherwise the last 3 characters.
     */
    public function birthMunicipalityCode(): BirthMunicipalityCode
    {
        return BirthMunicipalityCode::fromString(
            $this->isOverseas() ? '0' . substr($this->value, 3) : substr($this->value, 2),
        );
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

    private function isOverseas(): bool
    {
        return str_starts_with($this->value, self::OVERSEAS_PREFIX);
    }
}
