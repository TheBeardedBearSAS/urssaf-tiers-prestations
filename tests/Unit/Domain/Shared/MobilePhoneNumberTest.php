<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\MobilePhoneNumber;

final class MobilePhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidPhones(): iterable
    {
        yield 'national 6' => ['0605040302'];
        yield 'national 7' => ['0705040302'];
        yield 'international' => ['+33605040302'];
    }

    #[DataProvider('provideValidPhones')]
    public function testValidPhones(string $phone): void
    {
        $number = MobilePhoneNumber::fromString($phone);

        static::assertSame($phone, $number->value());
        static::assertSame(['value' => '***'], $number->__debugInfo());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPhones(): iterable
    {
        yield 'landline' => ['0105040302'];
        yield 'mobile 8' => ['0805040302'];
        yield 'too short' => ['060504030'];
        yield 'too long' => ['06050403021'];
        yield 'with spaces' => ['06 05 04 03 02'];
        yield 'international with zero' => ['+330605040302'];
        yield 'prefix' => ['x0605040302'];
        yield 'trailing newline' => ["0605040302\n"];
    }

    #[DataProvider('provideInvalidPhones')]
    public function testInvalidPhones(string $phone): void
    {
        $this->expectExceptionObject(
            InvalidValue::invalidFormat('mobile_phone_number', '0 or +33 followed by 6 or 7 and 8 digits'),
        );

        MobilePhoneNumber::fromString($phone);
    }

    public function testPhoneEquality(): void
    {
        static::assertTrue(MobilePhoneNumber::fromString('0605040302')->equals(MobilePhoneNumber::fromString('0605040302')));
        static::assertFalse(MobilePhoneNumber::fromString('0605040302')->equals(MobilePhoneNumber::fromString('+33605040302')));
    }
}
