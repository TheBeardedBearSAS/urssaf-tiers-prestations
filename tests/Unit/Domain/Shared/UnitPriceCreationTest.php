<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;

final class UnitPriceCreationTest extends TestCase
{
    public function testFromThousandths(): void
    {
        static::assertSame(0, UnitPrice::fromThousandths(0)->thousandths());
        static::assertSame(5515, UnitPrice::fromThousandths(5515)->thousandths());
    }

    public function testFromThousandthsRejectsNegative(): void
    {
        $this->expectExceptionObject(InvalidValue::negative('unit_price'));

        UnitPrice::fromThousandths(-1);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function provideDecimalStrings(): iterable
    {
        yield 'zero' => ['0', 0, '0.00'];
        yield 'integer' => ['5', 5000, '5.00'];
        yield 'one decimal' => ['5.5', 5500, '5.50'];
        yield 'two decimals' => ['5.51', 5510, '5.51'];
        yield 'three decimals' => ['5.515', 5515, '5.515'];
        yield 'max integer digits' => ['999999999999.999', 999_999_999_999_999, '999999999999.999'];
    }

    #[DataProvider('provideDecimalStrings')]
    public function testFromDecimalString(string $value, int $thousandths, string $formatted): void
    {
        $price = UnitPrice::fromDecimalString($value);

        static::assertSame($thousandths, $price->thousandths());
        static::assertSame($formatted, $price->toDecimalString());
        static::assertSame($formatted, (string) $price);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidDecimalStrings(): iterable
    {
        yield 'four decimals' => ['1.0001', 'unit_price.too_many_decimals'];
        yield 'too many integer digits' => ['1000000000000', 'unit_price.too_large'];
        yield 'negative' => ['-1', 'unit_price.negative'];
    }

    #[DataProvider('provideInvalidDecimalStrings')]
    public function testFromDecimalStringRejectsInvalidInput(string $value, string $code): void
    {
        try {
            UnitPrice::fromDecimalString($value);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testTooManyDecimalsReportsThree(): void
    {
        $this->expectExceptionObject(InvalidValue::tooManyDecimals('unit_price', 3));

        UnitPrice::fromDecimalString('1.1234');
    }

    public function testFromMoney(): void
    {
        static::assertSame(5510, UnitPrice::fromMoney(Money::fromCents(551))->thousandths());
    }

    public function testFromMoneyRejectsOverflow(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLarge('unit_price'));

        UnitPrice::fromMoney(Money::fromCents(PHP_INT_MAX));
    }
}
