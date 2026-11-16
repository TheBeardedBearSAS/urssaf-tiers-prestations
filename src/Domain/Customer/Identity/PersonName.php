<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Birth name, usage name or first names of a customer (nomNaissance, nomUsage, prenoms).
 *
 * DGFiP rules: 1 to 80 characters, unaccented ASCII letters, apostrophes, hyphens and spaces only, starting and
 * ending with a letter. The value is kept verbatim: transliteration of accented input is the caller's job.
 *
 * Violation codes (<subject> = birth_name | usage_name | first_names): "<subject>.empty",
 * "<subject>.invalid_characters", "<subject>.too_long", "<subject>.invalid_format".
 */
final readonly class PersonName
{
    public const MAX_LENGTH = 80;
    private const ALLOWED_CHARACTERS = "/^[A-Za-z' \\-]+$/D";
    private const PATTERN = "/^[A-Za-z](?:[A-Za-z' \\-]*[A-Za-z])?$/D";

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue
     */
    public static function birthName(string $value): self
    {
        return self::create('birth_name', $value);
    }

    /**
     * @throws InvalidValue
     */
    public static function usageName(string $value): self
    {
        return self::create('usage_name', $value);
    }

    /**
     * Usage first name(s), separated by a space.
     *
     * @throws InvalidValue
     */
    public static function firstNames(string $value): self
    {
        return self::create('first_names', $value);
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

    private static function create(string $subject, string $value): self
    {
        if ($value === '') {
            throw InvalidValue::empty($subject);
        }
        if (preg_match(self::ALLOWED_CHARACTERS, $value) !== 1) {
            throw InvalidValue::because(
                $subject . '.invalid_characters',
                \sprintf(
                    'Invalid %s: only unaccented ASCII letters, apostrophes, hyphens and spaces are allowed; '
                    . 'transliterate accented characters to ASCII first (e.g. "é" to "e").',
                    $subject,
                ),
            );
        }
        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::tooLong($subject, self::MAX_LENGTH);
        }
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat($subject, 'must start and end with a letter');
        }

        return new self($value);
    }
}
