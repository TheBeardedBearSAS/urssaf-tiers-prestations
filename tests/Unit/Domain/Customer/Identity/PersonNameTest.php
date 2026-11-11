<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Identity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\PersonName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class PersonNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidNames(): iterable
    {
        yield 'single letter' => ['A'];
        yield 'two letters' => ['Ab'];
        yield 'official birth name' => ['Durand'];
        yield 'hyphen' => ['Eric-Antoine'];
        yield 'apostrophe' => ["D'Artagnan"];
        yield 'spaces' => ['Jean Pierre Marie'];
        yield 'lower case' => ['de la fontaine'];
        yield 'max length' => [str_repeat('a', 80)];
    }

    #[DataProvider('provideValidNames')]
    public function testValidNames(string $value): void
    {
        static::assertSame($value, PersonName::birthName($value)->value());
        static::assertSame($value, PersonName::usageName($value)->value());
        static::assertSame(['value' => '***'], PersonName::firstNames($value)->__debugInfo());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidNames(): iterable
    {
        yield 'empty' => ['', 'empty'];
        yield 'accent' => ['Andre Benoît', 'invalid_characters'];
        yield 'digit' => ['Louis 14', 'invalid_characters'];
        yield 'dot' => ['St. Clair', 'invalid_characters'];
        yield 'trailing newline' => ["Durand\n", 'invalid_characters'];
        yield 'leading newline' => ["\nDurand", 'invalid_characters'];
        yield 'too long' => [str_repeat('a', 81), 'too_long'];
        yield 'starts with hyphen' => ['-Durand', 'invalid_format'];
        yield 'ends with apostrophe' => ["Durand'", 'invalid_format'];
        yield 'leading space' => [' Durand', 'invalid_format'];
        yield 'trailing space' => ['Durand ', 'invalid_format'];
        yield 'only space' => [' ', 'invalid_format'];
        yield 'only hyphen' => ['-', 'invalid_format'];
    }

    #[DataProvider('provideInvalidNames')]
    public function testInvalidNamesUseTheSubjectOfTheFactory(string $value, string $reason): void
    {
        foreach (['birth_name', 'usage_name', 'first_names'] as $subject) {
            try {
                self::create($subject, $value);
                static::fail('An invalid name must be rejected.');
            } catch (InvalidValue $exception) {
                static::assertSame($subject . '.' . $reason, $exception->violationCode());
                static::assertStringContainsString($subject, $exception->getMessage());
            }
        }
    }

    public function testMessages(): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'birth_name.invalid_characters',
            'Invalid birth_name: only unaccented ASCII letters, apostrophes, hyphens and spaces are allowed; '
            . 'transliterate accented characters to ASCII first (e.g. "é" to "e").',
        ));

        PersonName::birthName('Hélène');
    }

    public function testFormatAndLengthMessages(): void
    {
        $tooLong = InvalidValue::tooLong('usage_name', 80);
        try {
            PersonName::usageName(str_repeat('b', 81));
            static::fail('Expected too long.');
        } catch (InvalidValue $exception) {
            static::assertSame($tooLong->getMessage(), $exception->getMessage());
            static::assertSame(['max_length' => 80], $exception->params());
        }

        $this->expectExceptionObject(InvalidValue::invalidFormat('first_names', 'must start and end with a letter'));

        PersonName::firstNames('Eric-');
    }

    public function testEquality(): void
    {
        static::assertTrue(PersonName::birthName('Durand')->equals(PersonName::usageName('Durand')));
        static::assertFalse(PersonName::birthName('Durand')->equals(PersonName::birthName('DURAND')));
    }

    private static function create(string $subject, string $value): PersonName
    {
        return match ($subject) {
            'birth_name' => PersonName::birthName($value),
            'usage_name' => PersonName::usageName($value),
            default => PersonName::firstNames($value),
        };
    }
}
