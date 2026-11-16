<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\MoneyEncoding;

final class MoneyEncodingTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function moneyCents(): iterable
    {
        yield 'invoice total' => [1102, '11.02'];
        yield '0.1 + 0.2' => [10 + 20, '0.30'];
        yield 'zero' => [0, '0.00'];
        yield 'round' => [200_000, '2000.00'];
        yield 'single decimal' => [50, '0.50'];
    }

    #[DataProvider('moneyCents')]
    public function testItEncodesMoneyWithoutFloatDrift(int $cents, string $expected): void
    {
        static::assertSame($expected, JsonEncoder::encode(MoneyEncoding::encodeMoney(Money::fromCents($cents))));
    }

    public function testItEncodesUnitPricesWithThreeDecimals(): void
    {
        static::assertSame('5.515', JsonEncoder::encode(MoneyEncoding::encodeUnitPrice(UnitPrice::fromDecimalString('5.515'))));
    }

    public function testItEncodesQuantities(): void
    {
        static::assertSame('1.75', JsonEncoder::encode(MoneyEncoding::encodeQuantity(Quantity::fromDecimalString('1.75'))));
        static::assertSame('2', JsonEncoder::encode(MoneyEncoding::encodeQuantity(Quantity::fromInt(2))));
    }

    /**
     * @return iterable<string, array{int|float|string, int}>
     */
    public static function decodableAmounts(): iterable
    {
        yield 'float 11.02' => [11.02, 1102];
        yield 'float 5.51' => [5.51, 551];
        yield 'float sum 0.1 + 0.2' => [0.1 + 0.2, 30];
        yield 'float rounded half up' => [0.125, 13];
        yield 'integer' => [2000, 200_000];
        yield 'zero' => [0, 0];
        yield 'numeric string' => ['12.50', 1250];
        yield 'float with one decimal' => [0.5, 50];
    }

    #[DataProvider('decodableAmounts')]
    public function testItDecodesAmountsWithoutFloatDrift(int|float|string $wire, int $expectedCents): void
    {
        static::assertSame($expectedCents, MoneyEncoding::decodeMoney($wire)->cents());
    }

    /**
     * @return iterable<string, array{int|float|string}>
     */
    public static function undecodableAmounts(): iterable
    {
        yield 'negative' => [-1];
        yield 'negative float' => [-0.5];
        yield 'not a number' => ['abc'];
        yield 'too many decimals in a string' => ['1.234'];
        yield 'infinite' => [INF];
        yield 'not a number float' => [NAN];
    }

    #[DataProvider('undecodableAmounts')]
    public function testItRejectsUndecodableAmounts(int|float|string $wire): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        MoneyEncoding::decodeMoney($wire);
    }

    #[DataProvider('nonFiniteAmounts')]
    public function testItNamesNonFiniteAmounts(float $wire): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);
        $this->expectExceptionMessage('amount is not a finite number');

        MoneyEncoding::decodeMoney($wire);
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function nonFiniteAmounts(): iterable
    {
        yield 'infinite' => [INF];
        yield 'not a number' => [NAN];
    }
}
