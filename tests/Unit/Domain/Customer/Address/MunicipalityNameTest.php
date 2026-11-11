<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Address;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class MunicipalityNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidAddressNames(): iterable
    {
        yield 'official example' => ['LYON 01'];
        yield 'hyphen and apostrophe' => ["L'Hay-les-Roses"];
        yield 'accents' => ['Saint-Étienne'];
        yield 'single character' => ['Y'];
        yield 'max length' => [str_repeat('é', 50)];
    }

    #[DataProvider('provideValidAddressNames')]
    public function testValidAddressNames(string $value): void
    {
        $name = MunicipalityName::ofAddress($value);

        static::assertSame($value, $name->value());
        static::assertSame(['value' => '***'], $name->__debugInfo());
        static::assertTrue($name->equals(MunicipalityName::ofBirthPlace($value)));
        static::assertFalse($name->equals(MunicipalityName::ofAddress('Other')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidAddressNames(): iterable
    {
        yield 'empty' => ['', 'municipality_name.empty'];
        yield 'blank' => ['   ', 'municipality_name.empty'];
        yield 'semicolon' => ['Lyon;01', 'municipality_name.invalid_characters'];
        yield 'dot' => ['St. Tropez', 'municipality_name.invalid_characters'];
        yield 'trailing newline' => ["Lyon\n", 'municipality_name.invalid_characters'];
        yield 'leading newline' => ["\nLyon", 'municipality_name.invalid_characters'];
        yield 'invalid utf-8' => ["Lyon\xC3", 'municipality_name.invalid_characters'];
        yield 'too long' => [str_repeat('é', 51), 'municipality_name.too_long'];
    }

    #[DataProvider('provideInvalidAddressNames')]
    public function testInvalidAddressNames(string $value, string $code): void
    {
        try {
            MunicipalityName::ofAddress($value);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidBirthNames(): iterable
    {
        yield 'official example' => ['Lyon'];
        yield 'punctuation' => ['St. Tropez (Var)'];
        yield 'max length' => [str_repeat('a', 50)];
    }

    #[DataProvider('provideValidBirthNames')]
    public function testValidBirthNames(string $value): void
    {
        static::assertSame($value, MunicipalityName::ofBirthPlace($value)->value());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidBirthNames(): iterable
    {
        yield 'blank' => [' ', 'birth_municipality_name.empty'];
        yield 'semicolon' => ['Lyon;', 'birth_municipality_name.invalid_characters'];
        yield 'tab' => ["Ly\ton", 'birth_municipality_name.invalid_characters'];
        yield 'trailing newline' => ["Lyon\n", 'birth_municipality_name.invalid_characters'];
        yield 'leading newline' => ["\nLyon", 'birth_municipality_name.invalid_characters'];
        yield 'too long' => [str_repeat('a', 51), 'birth_municipality_name.too_long'];
    }

    #[DataProvider('provideInvalidBirthNames')]
    public function testInvalidBirthNames(string $value, string $code): void
    {
        try {
            MunicipalityName::ofBirthPlace($value);
            static::fail('Expected an invalid value.');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testMessages(): void
    {
        static::assertSame(
            InvalidValue::tooLong('municipality_name', 50)->getMessage(),
            self::messageOf(static fn(): MunicipalityName => MunicipalityName::ofAddress(str_repeat('a', 51))),
        );

        $this->expectExceptionObject(InvalidValue::because(
            'birth_municipality_name.invalid_characters',
            'Invalid birth_municipality_name: the value contains characters that are not allowed.',
        ));

        MunicipalityName::ofBirthPlace('a;b');
    }

    /**
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    private static function messageOf(Closure $action): string
    {
        try {
            $action();
        } catch (InvalidValue $exception) {
            return $exception->getMessage();
        }

        return '';
    }
}
