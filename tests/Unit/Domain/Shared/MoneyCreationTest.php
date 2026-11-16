<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

final class MoneyCreationTest extends TestCase
{
    public function testFromCents(): void
    {
        static::assertSame(0, Money::fromCents(0)->cents());
        static::assertSame(1102, Money::fromCents(1102)->cents());
    }

    public function testFromCentsRejectsNegative(): void
    {
        $this->expectExceptionObject(InvalidValue::negative('money'));

        Money::fromCents(-1);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideDecimalStrings(): iterable
    {
        yield 'integer' => ['2000', 200_000];
        yield 'one decimal' => ['0.5', 50];
        yield 'two decimals' => ['11.02', 1102];
        yield 'max integer digits' => ['999999999999999.99', 99_999_999_999_999_999];
    }

    #[DataProvider('provideDecimalStrings')]
    public function testFromDecimalString(string $amount, int $cents): void
    {
        static::assertSame($cents, Money::fromDecimalString($amount)->cents());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDecimalStrings(): iterable
    {
        yield 'three decimals' => ['1.001', 'money.too_many_decimals'];
        yield 'too many integer digits' => ['1000000000000000', 'money.too_large'];
        yield 'negative' => ['-0.01', 'money.negative'];
        yield 'format' => ['1,00', 'money.invalid_format'];
    }

    #[DataProvider('provideInvalidDecimalStrings')]
    public function testFromDecimalStringRejectsInvalidInput(string $amount, string $code): void
    {
        try {
            Money::fromDecimalString($amount);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
            static::assertStringContainsString('money', $exception->getMessage());
        }
    }

    public function testTooManyDecimalsReportsTwo(): void
    {
        $this->expectExceptionObject(InvalidValue::tooManyDecimals('money', 2));

        Money::fromDecimalString('1.123');
    }

    public function testToDecimalString(): void
    {
        static::assertSame('11.02', Money::fromCents(1102)->toDecimalString());
        static::assertSame('0.50', Money::fromCents(50)->toDecimalString());
        static::assertSame('0.00', Money::fromCents(0)->toDecimalString());
    }
}
