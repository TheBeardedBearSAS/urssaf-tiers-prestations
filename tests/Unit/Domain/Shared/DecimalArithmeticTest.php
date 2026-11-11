<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Decimal;

final class DecimalArithmeticTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function provideFormats(): iterable
    {
        yield 'cents' => [1250, 2, 2, '12.50'];
        yield 'small cents' => [5, 2, 2, '0.05'];
        yield 'zero cents' => [0, 2, 2, '0.00'];
        yield 'trimmed integer' => [2000, 3, 0, '2'];
        yield 'trimmed' => [1750, 3, 0, '1.75'];
        yield 'not trimmed' => [125, 3, 0, '0.125'];
        yield 'min two decimals' => [5500, 3, 2, '5.50'];
        yield 'min two decimals three needed' => [5515, 3, 2, '5.515'];
        yield 'min two decimals integer' => [5000, 3, 2, '5.00'];
        yield 'large' => [123_456_789, 3, 0, '123456.789'];
    }

    #[DataProvider('provideFormats')]
    public function testFormat(int $scaled, int $decimals, int $minDecimals, string $expected): void
    {
        static::assertSame($expected, Decimal::format($scaled, $decimals, $minDecimals));
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function provideDivisions(): iterable
    {
        yield 'exact' => [3000, 1000, 3];
        yield 'below half' => [3499, 1000, 3];
        yield 'half' => [3500, 1000, 4];
        yield 'above half' => [3501, 1000, 4];
        yield 'odd divisor below half' => [1, 3, 0];
        yield 'odd divisor above half' => [2, 3, 1];
        yield 'zero' => [0, 1000, 0];
    }

    #[DataProvider('provideDivisions')]
    public function testDivideRoundingHalfUp(int $dividend, int $divisor, int $expected): void
    {
        static::assertSame($expected, Decimal::divideRoundingHalfUp($dividend, $divisor));
    }

    public function testMultiply(): void
    {
        static::assertSame(42, Decimal::multiply(6, 7, 'money'));
        static::assertSame(0, Decimal::multiply(0, PHP_INT_MAX, 'money'));
        static::assertSame(PHP_INT_MAX, Decimal::multiply(7, intdiv(PHP_INT_MAX, 7), 'money'));
        static::assertSame(PHP_INT_MAX, Decimal::multiply(1, PHP_INT_MAX, 'money'));
    }

    public function testMultiplyRejectsOverflow(): void
    {
        try {
            Decimal::multiply(7, intdiv(PHP_INT_MAX, 7) + 1, 'quantity');
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('quantity.too_large', $exception->violationCode());
        }
    }
}
