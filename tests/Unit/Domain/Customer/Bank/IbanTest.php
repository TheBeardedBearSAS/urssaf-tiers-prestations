<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Bank;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Iban;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class IbanTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideValidIbans(): iterable
    {
        yield 'official example' => ['FR7630006000011234567890189', 'FR7630006000011234567890189'];
        yield 'German' => ['DE89370400440532013000', 'DE89370400440532013000'];
        yield 'Belgian' => ['BE68539007547034', 'BE68539007547034'];
        yield 'Norwegian, 15 characters' => ['NO9386011117947', 'NO9386011117947'];
        yield 'minimum length' => ['XX90ABCD1234567', 'XX90ABCD1234567'];
        yield 'maximum length' => ['LC87ABCD11111111111111111111111111', 'LC87ABCD11111111111111111111111111'];
        yield 'letters in the account' => ['GB82WEST12345698765432', 'GB82WEST12345698765432'];
        yield 'Maltese, letter after other characters' => ['MT84MALT011000012345MTLCAST001S', 'MT84MALT011000012345MTLCAST001S'];
        yield 'spaces and lower case' => ['fr76 3000 6000 0112 3456 7890 189', 'FR7630006000011234567890189'];
    }

    #[DataProvider('provideValidIbans')]
    public function testValidIbans(string $input, string $expected): void
    {
        $iban = Iban::fromString($input);

        static::assertSame($expected, $iban->value());
        static::assertSame(['value' => '***'], $iban->__debugInfo());
        static::assertTrue($iban->equals(Iban::fromString($expected)));
        static::assertFalse($iban->equals(Iban::fromString('FR1420041010050500013M02606')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMalformedIbans(): iterable
    {
        yield 'empty' => [''];
        yield '14 characters' => ['NO938601111794'];
        yield '35 characters' => ['LC87ABCD111111111111111111111111111'];
        yield 'digit in country code' => ['F17630006000011234567890189'];
        yield 'letter in check digits' => ['FRA630006000011234567890189'];
        yield 'symbol' => ['FR76-30006000011234567890189'];
        yield 'prefixed' => ['-FR7630006000011234567890189'];
        yield 'trailing newline' => ["FR7630006000011234567890189\n"];
    }

    #[DataProvider('provideMalformedIbans')]
    public function testMalformedIbans(string $input): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('iban', '15 to 34 characters: country code, check digits, account'));

        Iban::fromString($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWrongChecksums(): iterable
    {
        yield 'last digit changed' => ['FR7630006000011234567890188'];
        yield 'check digits changed' => ['FR7730006000011234567890189'];
        yield 'country changed' => ['ES7630006000011234567890189'];
        yield 'swapped digits' => ['DE89370400440532013000'[0] . 'E89370400440532031000'];
        yield 'check digits 00' => ['GB00WEST12345698765432'];
    }

    #[DataProvider('provideWrongChecksums')]
    public function testWrongChecksums(string $input): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'iban.invalid_checksum',
            'Invalid iban: the check digits do not match (ISO 13616).',
        ));

        Iban::fromString($input);
    }
}
