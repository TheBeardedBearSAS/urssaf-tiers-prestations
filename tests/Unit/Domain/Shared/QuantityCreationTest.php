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
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;

final class QuantityCreationTest extends TestCase
{
    public function testFromThousandths(): void
    {
        static::assertSame(1, Quantity::fromThousandths(1)->thousandths());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideNonPositive(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('provideNonPositive')]
    public function testFromThousandthsRejectsNonPositive(int $value): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('quantity'));

        Quantity::fromThousandths($value);
    }

    #[DataProvider('provideNonPositive')]
    public function testFromIntRejectsNonPositive(int $value): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('quantity'));

        Quantity::fromInt($value);
    }

    public function testFromInt(): void
    {
        static::assertSame(1000, Quantity::fromInt(1)->thousandths());
        static::assertSame(3000, Quantity::fromInt(3)->thousandths());
    }

    public function testFromIntRejectsOverflow(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLarge('quantity'));

        Quantity::fromInt(intdiv(PHP_INT_MAX, 1000) + 1);
    }

    public function testFromIntAcceptsTheLargestSupportedValue(): void
    {
        static::assertSame(intdiv(PHP_INT_MAX, 1000) * 1000, Quantity::fromInt(intdiv(PHP_INT_MAX, 1000))->thousandths());
    }

    public function testFromIntRejectsNegativeValuesBelowTheIntegerRange(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLarge('quantity'));

        Quantity::fromInt(intdiv(PHP_INT_MIN, 1000) - 1);
    }

    public function testFromIntRejectsTheSmallestNegativeValueAsNotPositive(): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('quantity'));

        Quantity::fromInt(intdiv(PHP_INT_MIN, 1000));
    }

    public function testToDecimalString(): void
    {
        static::assertSame('2', Quantity::fromInt(2)->toDecimalString());
        static::assertSame('1.75', Quantity::fromThousandths(1750)->toDecimalString());
        static::assertSame('0.125', Quantity::fromThousandths(125)->toDecimalString());
        static::assertSame('10', Quantity::fromThousandths(10_000)->toDecimalString());
    }
}
