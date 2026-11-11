<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Decimal;

final class DecimalParseTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function provideValidInputs(): iterable
    {
        yield 'zero' => ['0', 2, 3, 0];
        yield 'integer' => ['12', 2, 3, 1200];
        yield 'one decimal' => ['12.5', 2, 3, 1250];
        yield 'max decimals' => ['12.05', 2, 3, 1205];
        yield 'zero with decimals' => ['0.07', 2, 3, 7];
        yield 'max integer digits' => ['999', 2, 3, 99_900];
        yield 'three decimals scale' => ['1.75', 3, 3, 1750];
    }

    #[DataProvider('provideValidInputs')]
    public function testParse(string $value, int $decimals, int $maxIntegerDigits, int $expected): void
    {
        static::assertSame($expected, Decimal::parse($value, 'money', $decimals, $maxIntegerDigits));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidInputs(): iterable
    {
        yield 'empty' => ['', 'money.invalid_format'];
        yield 'leading zero' => ['01', 'money.invalid_format'];
        yield 'comma' => ['1,5', 'money.invalid_format'];
        yield 'dot only' => ['1.', 'money.invalid_format'];
        yield 'no integer part' => ['.5', 'money.invalid_format'];
        yield 'plus sign' => ['+1', 'money.invalid_format'];
        yield 'exponent' => ['1e3', 'money.invalid_format'];
        yield 'leading space' => [' 1', 'money.invalid_format'];
        yield 'trailing space' => ['1 ', 'money.invalid_format'];
        yield 'trailing newline' => ["1\n", 'money.invalid_format'];
        yield 'letters' => ['abc', 'money.invalid_format'];
        yield 'negative' => ['-1.5', 'money.negative'];
        yield 'too many decimals' => ['1.005', 'money.too_many_decimals'];
        yield 'too many decimals even zeros' => ['1.000', 'money.too_many_decimals'];
        yield 'too many integer digits' => ['1000', 'money.too_large'];
    }

    #[DataProvider('provideInvalidInputs')]
    public function testParseRejectsInvalidInput(string $value, string $code): void
    {
        try {
            Decimal::parse($value, 'money', 2, 3);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testTooManyDecimalsReportsTheLimit(): void
    {
        try {
            Decimal::parse('1.1234', 'quantity', 3, 3);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame(['max_decimals' => 3], $exception->params());
        }
    }
}
