<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * INSEE country code: exactly 5 digits (codePays, codePaysNaissance). France is "99100".
 */
final readonly class CountryCode implements Stringable
{
    public const FRANCE = '99100';
    private const SUBJECT = 'country_code';
    private const PATTERN = '/^\d{5}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, '5 digits');
        }

        return new self($value);
    }

    public static function france(): self
    {
        return new self(self::FRANCE);
    }

    public function isFrance(): bool
    {
        return $this->value === self::FRANCE;
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
