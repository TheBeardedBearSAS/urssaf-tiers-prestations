<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Address;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class SimpleAddressValuesTest extends TestCase
{
    public function testPostalCode(): void
    {
        $code = PostalCode::fromString('69001');

        static::assertSame('69001', $code->value());
        static::assertSame(['value' => '***'], $code->__debugInfo());
        static::assertTrue($code->equals(PostalCode::fromString('69001')));
        static::assertFalse($code->equals(PostalCode::fromString('69002')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPostalCodes(): iterable
    {
        yield 'four digits' => ['6900'];
        yield 'six digits' => ['690011'];
        yield 'letter' => ['2A000'];
        yield 'prefixed' => ['x69001'];
        yield 'trailing newline' => ["69001\n"];
    }

    #[DataProvider('provideInvalidPostalCodes')]
    public function testInvalidPostalCode(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('postal_code', '5 digits'));

        PostalCode::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidStreetNumbers(): iterable
    {
        yield 'one digit' => ['8'];
        yield 'four digits' => ['1234'];
        yield 'leading zero' => ['08'];
        yield 'zeros then one' => ['0001'];
        yield 'ten' => ['10'];
    }

    #[DataProvider('provideValidStreetNumbers')]
    public function testValidStreetNumbers(string $value): void
    {
        $number = StreetNumber::fromString($value);

        static::assertSame($value, $number->value());
        static::assertSame(['value' => '***'], $number->__debugInfo());
        static::assertTrue($number->equals(StreetNumber::fromString($value)));
        static::assertFalse($number->equals(StreetNumber::fromString('9999')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidStreetNumbers(): iterable
    {
        yield 'empty' => ['', 'street_number.invalid_format'];
        yield 'five digits' => ['12345', 'street_number.invalid_format'];
        yield 'letter' => ['8B', 'street_number.invalid_format'];
        yield 'prefixed' => ['x8', 'street_number.invalid_format'];
        yield 'trailing newline' => ["8\n", 'street_number.invalid_format'];
        yield 'zero' => ['0', 'street_number.zero'];
        yield 'double zero' => ['00', 'street_number.zero'];
        yield 'triple zero' => ['000', 'street_number.zero'];
        yield 'quadruple zero' => ['0000', 'street_number.zero'];
    }

    #[DataProvider('provideInvalidStreetNumbers')]
    public function testInvalidStreetNumbers(string $value, string $code): void
    {
        try {
            StreetNumber::fromString($value);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testStreetNumberMessages(): void
    {
        $this->expectExceptionObject(InvalidValue::because('street_number.zero', 'Invalid street_number: the value must not be zero.'));

        StreetNumber::fromString('0');
    }
}
