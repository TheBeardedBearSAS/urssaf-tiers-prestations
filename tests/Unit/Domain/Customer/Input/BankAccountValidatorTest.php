<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\BankAccount;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BankAccountDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BankAccountValidator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\ViolationCollector;

final class BankAccountValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{BankAccountDraft, list<string>}>
     */
    public static function provideDrafts(): iterable
    {
        yield 'valid' => [new BankAccountDraft('fr76 3000 6000 0112 3456 7890 189', 'bnpafrpp', 'Mme Jeanne Martin'), []];
        yield 'nothing' => [new BankAccountDraft(null, '', null), ['iban: iban.required', 'bic: bic.required', 'holder: account_holder.required']];
        yield 'iban' => [new BankAccountDraft('FR76', 'BNPAFRPP', 'Jeanne'), ['iban: iban.invalid_format']];
        yield 'bic' => [new BankAccountDraft('FR7630006000011234567890189', 'BNPA', 'Jeanne'), ['bic: bic.invalid_format']];
        yield 'holder' => [new BankAccountDraft('FR7630006000011234567890189', 'BNPAFRPP', ' '), ['holder: account_holder.empty']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideDrafts')]
    public function testDrafts(BankAccountDraft $draft, array $expected): void
    {
        $validator = new BankAccountValidator();
        static::assertSame($expected, ViolationSummary::of($validator->validate($draft)));

        $collector = new ViolationCollector();
        $account = $validator->parse($draft, $collector);
        static::assertSame($expected === [], $account instanceof BankAccount);
        static::assertSame($expected, ViolationSummary::of($collector->result()));
    }

    public function testParsedValues(): void
    {
        $account = (new BankAccountValidator())->parse(new BankAccountDraft('fr76 3000 6000 0112 3456 7890 189', 'bnpafrpp', 'Mme Jeanne Martin'), new ViolationCollector());

        static::assertNotNull($account);
        static::assertSame('FR7630006000011234567890189', $account->iban()->value());
        static::assertSame('BNPAFRPP', $account->bic()->value());
        static::assertSame('Mme Jeanne Martin', $account->holder()->value());
    }
}
