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
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;

final class MoneyArithmeticTest extends TestCase
{
    public function testAdd(): void
    {
        $a = Money::fromCents(551);

        static::assertSame(1102, $a->add(Money::fromCents(551))->cents());
        static::assertSame(551, $a->cents());
        static::assertSame(PHP_INT_MAX, Money::fromCents(PHP_INT_MAX - 1)->add(Money::fromCents(1))->cents());
    }

    public function testAddRejectsOverflow(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLarge('money'));

        Money::fromCents(PHP_INT_MAX)->add(Money::fromCents(1));
    }

    public function testSubtract(): void
    {
        static::assertSame(825, Money::fromCents(1100)->subtract(Money::fromCents(275))->cents());
        static::assertSame(0, Money::fromCents(275)->subtract(Money::fromCents(275))->cents());
    }

    public function testSubtractRejectsNegativeResult(): void
    {
        $this->expectExceptionObject(InvalidValue::negative('money'));

        Money::fromCents(274)->subtract(Money::fromCents(275));
    }

    /**
     * @return iterable<string, array{int, string, int}>
     */
    public static function provideMultiplications(): iterable
    {
        yield 'by one' => [551, '1', 551];
        yield 'exact' => [1000, '1.75', 1750];
        yield 'round down' => [333, '0.5', 167];
        yield 'below half' => [101, '0.004', 0];
        yield 'half up' => [125, '0.004', 1];
        yield 'above half' => [126, '0.004', 1];
        yield 'just below half' => [124, '0.004', 0];
    }

    #[DataProvider('provideMultiplications')]
    public function testMultipliedBy(int $cents, string $quantity, int $expected): void
    {
        static::assertSame($expected, Money::fromCents($cents)->multipliedBy(Quantity::fromDecimalString($quantity))->cents());
    }

    public function testMultipliedByRejectsOverflow(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLarge('money'));

        Money::fromCents(PHP_INT_MAX)->multipliedBy(Quantity::fromInt(2));
    }

    public function testIsWithinTolerance(): void
    {
        $total = Money::fromCents(1102);
        $tolerance = Money::fromCents(Money::URSSAF_TOLERANCE_CENTS);

        static::assertTrue($total->isWithinTolerance(Money::fromCents(1102), $tolerance));
        static::assertTrue($total->isWithinTolerance(Money::fromCents(1107), $tolerance));
        static::assertTrue($total->isWithinTolerance(Money::fromCents(1097), $tolerance));
        static::assertFalse($total->isWithinTolerance(Money::fromCents(1108), $tolerance));
        static::assertFalse($total->isWithinTolerance(Money::fromCents(1096), $tolerance));
    }

    public function testComparisons(): void
    {
        $amount = Money::fromCents(100);

        static::assertTrue($amount->isGreaterThan(Money::fromCents(99)));
        static::assertFalse($amount->isGreaterThan(Money::fromCents(100)));
        static::assertFalse($amount->isGreaterThan(Money::fromCents(101)));
        static::assertTrue($amount->equals(Money::fromDecimalString('1')));
        static::assertFalse($amount->equals(Money::fromCents(101)));
    }
}
