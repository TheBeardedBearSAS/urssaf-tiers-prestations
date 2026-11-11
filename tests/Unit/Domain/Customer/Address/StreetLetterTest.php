<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Address;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetLetter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class StreetLetterTest extends TestCase
{
    public function testStreetLetters(): void
    {
        foreach (range('A', 'Z') as $letter) {
            static::assertSame($letter, StreetLetter::fromString($letter)->value());
            static::assertSame($letter, (string) StreetLetter::fromString(strtolower($letter)));
        }
        static::assertTrue(StreetLetter::fromString('b')->equals(StreetLetter::fromString('B')));
        static::assertFalse(StreetLetter::fromString('B')->equals(StreetLetter::fromString('T')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidStreetLetters(): iterable
    {
        yield 'empty' => [''];
        yield 'two letters' => ['BT'];
        yield 'digit' => ['1'];
        yield 'accent' => ['É'];
        yield 'prefixed' => ['xB'];
        yield 'trailing newline' => ["B\n"];
    }

    #[DataProvider('provideInvalidStreetLetters')]
    public function testInvalidStreetLetters(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('street_letter', 'a single letter from A to Z'));

        StreetLetter::fromString($value);
    }
}
