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

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Strict conversions between non-negative decimal strings and scaled integers.
 *
 * @internal shared by Money, UnitPrice and Quantity
 */
final class Decimal
{
    private const ZERO = '0';
    private const START = 0;
    private const PATTERN = '/^(-?)(0|[1-9]\d*)(?:\.(\d+))?$/D';

    /**
     * Parses "12.5" with 2 decimals into 1250. Accepted syntax: digits, optional "." followed by at least one digit,
     * no sign, no leading zero, no exponent, no thousands separator, no surrounding whitespace.
     *
     * @throws InvalidValue
     */
    public static function parse(string $value, string $subject, int $decimals, int $maxIntegerDigits): int
    {
        $matches = [];
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw InvalidValue::invalidFormat($subject, 'decimal number with "." as separator');
        }
        if ($matches[1] === '-') {
            throw InvalidValue::negative($subject);
        }
        $fraction = $matches[3] ?? '';
        if (\strlen($fraction) > $decimals) {
            throw InvalidValue::tooManyDecimals($subject, $decimals);
        }
        if (\strlen($matches[2]) > $maxIntegerDigits) {
            throw InvalidValue::tooLarge($subject);
        }

        return (int) ($matches[2] . str_pad($fraction, $decimals, self::ZERO));
    }

    /**
     * Formats 1250 (scale 2) as "12.50"; with $minDecimals lower than $decimals trailing zeros are trimmed down to
     * $minDecimals digits (1750, scale 3, min 0 => "1.75").
     */
    public static function format(int $scaled, int $decimals, int $minDecimals): string
    {
        $digits = str_pad((string) $scaled, $decimals + 1, self::ZERO, STR_PAD_LEFT);
        $integer = substr($digits, self::START, -$decimals);
        $fraction = substr($digits, -$decimals);
        $trimmed = str_pad(rtrim($fraction, self::ZERO), $minDecimals, self::ZERO);

        return $trimmed === '' ? $integer : $integer . '.' . $trimmed;
    }

    /**
     * Divides a non-negative integer, rounding half up (away from zero).
     */
    public static function divideRoundingHalfUp(int $dividend, int $divisor): int
    {
        $quotient = intdiv($dividend, $divisor);

        return 2 * ($dividend % $divisor) >= $divisor ? $quotient + 1 : $quotient;
    }

    /**
     * Multiplies two non-negative integers.
     *
     * @throws InvalidValue when the product overflows the native integer range
     */
    public static function multiply(int $left, int $right, string $subject): int
    {
        if ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left)) {
            throw InvalidValue::tooLarge($subject);
        }

        return $left * $right;
    }
}
