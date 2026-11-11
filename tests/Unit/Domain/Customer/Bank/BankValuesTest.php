<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Bank;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\AccountHolder;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\BankAccount;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Bic;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Iban;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class BankValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideValidBics(): iterable
    {
        yield 'official example' => ['BNPAFRPPXXX', 'BNPAFRPPXXX'];
        yield 'eight characters' => ['BNPAFRPP', 'BNPAFRPP'];
        yield 'digits in location and branch' => ['DEUTDEFF500', 'DEUTDEFF500'];
        yield 'lower case and spaces' => ['bnpa fr pp xxx', 'BNPAFRPPXXX'];
    }

    #[DataProvider('provideValidBics')]
    public function testValidBics(string $input, string $expected): void
    {
        $bic = Bic::fromString($input);

        static::assertSame($expected, $bic->value());
        static::assertSame(['value' => '***'], $bic->__debugInfo());
        static::assertTrue($bic->equals(Bic::fromString($expected)));
        static::assertFalse($bic->equals(Bic::fromString('DEUTDEFF')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidBics(): iterable
    {
        yield 'seven characters' => ['BNPAFRP'];
        yield 'nine characters' => ['BNPAFRPPX'];
        yield 'ten characters' => ['BNPAFRPPXX'];
        yield 'twelve characters' => ['BNPAFRPPXXXX'];
        yield 'digit in bank code' => ['BNP1FRPPXXX'];
        yield 'symbol' => ['BNPAFRPP-XX'];
        yield 'prefixed' => ['-BNPAFRPP'];
        yield 'trailing newline' => ["BNPAFRPP\n"];
    }

    #[DataProvider('provideInvalidBics')]
    public function testInvalidBics(string $input): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('bic', '8 or 11 characters'));

        Bic::fromString($input);
    }

    public function testAccountHolder(): void
    {
        $holder = AccountHolder::fromString('Mme Jeanne Martin');

        static::assertSame('Mme Jeanne Martin', $holder->value());
        static::assertSame(['value' => '***'], $holder->__debugInfo());
        static::assertTrue($holder->equals(AccountHolder::fromString('Mme Jeanne Martin')));
        static::assertFalse($holder->equals(AccountHolder::fromString('M Jean Martin')));
        static::assertSame(str_repeat('é', 100), AccountHolder::fromString(str_repeat('é', 100))->value());
    }

    /**
     * @return iterable<string, array{string, InvalidValue}>
     */
    public static function provideInvalidHolders(): iterable
    {
        yield 'empty' => ['', InvalidValue::empty('account_holder')];
        yield 'blank' => ['  ', InvalidValue::empty('account_holder')];
        yield 'too long' => [str_repeat('é', 101), InvalidValue::tooLong('account_holder', 100)];
    }

    #[DataProvider('provideInvalidHolders')]
    public function testInvalidHolders(string $input, InvalidValue $expected): void
    {
        $this->expectExceptionObject($expected);

        AccountHolder::fromString($input);
    }

    public function testBankAccount(): void
    {
        $account = BankAccount::of(
            Iban::fromString('FR7630006000011234567890189'),
            Bic::fromString('BNPAFRPPXXX'),
            AccountHolder::fromString('Mme Jeanne Martin'),
        );

        static::assertSame('FR7630006000011234567890189', $account->iban()->value());
        static::assertSame('BNPAFRPPXXX', $account->bic()->value());
        static::assertSame('Mme Jeanne Martin', $account->holder()->value());
    }
}
