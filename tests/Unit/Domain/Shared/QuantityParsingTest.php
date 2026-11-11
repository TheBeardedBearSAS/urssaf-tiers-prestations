<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;

final class QuantityParsingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function provideDecimalStrings(): iterable
    {
        yield 'integer' => ['2', 2000, '2'];
        yield 'two decimals' => ['1.75', 1750, '1.75'];
        yield 'three decimals' => ['0.125', 125, '0.125'];
        yield 'smallest' => ['0.001', 1, '0.001'];
        yield 'trailing zeros' => ['1.500', 1500, '1.5'];
        yield 'max integer digits' => ['999999999999.999', 999_999_999_999_999, '999999999999.999'];
    }

    #[DataProvider('provideDecimalStrings')]
    public function testFromDecimalString(string $value, int $thousandths, string $formatted): void
    {
        $quantity = Quantity::fromDecimalString($value);

        static::assertSame($thousandths, $quantity->thousandths());
        static::assertSame($formatted, $quantity->toDecimalString());
        static::assertSame($formatted, (string) $quantity);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDecimalStrings(): iterable
    {
        yield 'zero' => ['0', 'quantity.not_positive'];
        yield 'zero decimals' => ['0.000', 'quantity.not_positive'];
        yield 'four decimals' => ['1.0001', 'quantity.too_many_decimals'];
        yield 'too many integer digits' => ['1000000000000', 'quantity.too_large'];
        yield 'negative' => ['-1', 'quantity.negative'];
    }

    #[DataProvider('provideInvalidDecimalStrings')]
    public function testFromDecimalStringRejectsInvalidInput(string $value, string $code): void
    {
        try {
            Quantity::fromDecimalString($value);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
            static::assertStringContainsString('quantity', $exception->getMessage());
        }
    }

    public function testTooManyDecimalsReportsThree(): void
    {
        $this->expectExceptionObject(InvalidValue::tooManyDecimals('quantity', 3));

        Quantity::fromDecimalString('1.1234');
    }

    public function testEquals(): void
    {
        static::assertTrue(Quantity::fromInt(1)->equals(Quantity::fromDecimalString('1.000')));
        static::assertFalse(Quantity::fromInt(1)->equals(Quantity::fromDecimalString('1.001')));
    }
}
