<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;

final class CountryCodeTest extends TestCase
{
    public function testCountryCodes(): void
    {
        $france = CountryCode::france();

        static::assertSame('99100', $france->value());
        static::assertSame('99100', (string) $france);
        static::assertTrue($france->isFrance());
        static::assertTrue(CountryCode::fromString('99100')->isFrance());
        static::assertFalse(CountryCode::fromString('99134')->isFrance());
        static::assertSame('99134', CountryCode::fromString('99134')->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCountryCodes(): iterable
    {
        yield 'four digits' => ['9910'];
        yield 'six digits' => ['991000'];
        yield 'letters' => ['FR'];
        yield 'prefix' => ['x99100'];
        yield 'trailing newline' => ["99100\n"];
    }

    #[DataProvider('provideInvalidCountryCodes')]
    public function testInvalidCountryCodes(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('country_code', '5 digits'));

        CountryCode::fromString($code);
    }

    public function testCountryEquality(): void
    {
        static::assertTrue(CountryCode::france()->equals(CountryCode::fromString('99100')));
        static::assertFalse(CountryCode::france()->equals(CountryCode::fromString('99134')));
    }
}
