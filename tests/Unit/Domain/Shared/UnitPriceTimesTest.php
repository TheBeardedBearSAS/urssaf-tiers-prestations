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
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;

final class UnitPriceTimesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function provideProducts(): iterable
    {
        yield 'official example' => ['5.51', '1', 551];
        yield 'exact' => ['20', '1.75', 3500];
        yield 'documented rounding' => ['22.335', '1.75', 3909];
        yield 'half up' => ['0.005', '1', 1];
        yield 'below half' => ['0.004', '1', 0];
        yield 'just below half' => ['0.001', '4.999', 0];
        yield 'exactly half from fractional' => ['0.001', '5', 1];
        yield 'zero price' => ['0', '3', 0];
    }

    #[DataProvider('provideProducts')]
    public function testTimes(string $price, string $quantity, int $expectedCents): void
    {
        static::assertSame($expectedCents, UnitPrice::fromDecimalString($price)->times(Quantity::fromDecimalString($quantity))->cents());
    }

    public function testTimesRejectsOverflow(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLarge('unit_price'));

        UnitPrice::fromDecimalString('999999999999.999')->times(Quantity::fromDecimalString('999999999999.999'));
    }

    public function testEquals(): void
    {
        static::assertTrue(UnitPrice::fromDecimalString('5.5')->equals(UnitPrice::fromThousandths(5500)));
        static::assertFalse(UnitPrice::fromDecimalString('5.5')->equals(UnitPrice::fromThousandths(5501)));
    }
}
