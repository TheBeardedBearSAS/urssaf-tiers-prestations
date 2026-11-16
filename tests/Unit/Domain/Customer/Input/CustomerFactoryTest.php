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
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\AddressDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BankAccountDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

final class CustomerFactoryTest extends TestCase
{
    public function testOfficialExampleIsValid(): void
    {
        $factory = new CustomerFactory();

        static::assertSame([], $factory->validate(CustomerFixtures::officialDraft())->all());
        static::assertSame('Durand', $factory->fromDraft(CustomerFixtures::officialDraft())->civilStatus()->birthName()->value());
    }

    public function testEverythingMissing(): void
    {
        $expected = [
            'civility: civility.required',
            'birthName: birth_name.required',
            'firstNames: first_names.required',
            'birthDate: birth_date.required',
            'birthPlace.countryCode: country_code.required',
            'mobilePhone: mobile_phone.required',
            'email: email.required',
            'address: postal_address.delivery_point_required',
            'address.municipalityName: municipality_name.required',
            'address.municipalityCode: municipality_code.required',
            'address.postalCode: postal_code.required',
            'address.countryCode: country_code.required',
            'bankAccount.iban: iban.required',
            'bankAccount.bic: bic.required',
            'bankAccount.holder: account_holder.required',
        ];
        $draft = new CustomerDraft(null, null, null, null, null, null, null, null, null, null);

        static::assertSame($expected, ViolationSummary::of((new CustomerFactory())->validate($draft)));
        try {
            (new CustomerFactory())->fromDraft($draft);
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $failure) {
            static::assertSame($expected, ViolationSummary::of($failure->result()));
        }
    }

    public function testRequiredMessage(): void
    {
        $draft = new CustomerDraft(null, null, null, null, null, null, null, null, null, null);
        $violation = (new CustomerFactory())->validate($draft)->all()[1];

        static::assertSame('The birth name is required.', $violation->message());
        static::assertTrue($violation->isError());
    }

    /**
     * One invalid or missing field at a time: exactly one violation, and no customer.
     *
     * @return iterable<string, array{CustomerDraft, string}>
     */
    public static function provideSingleViolations(): iterable
    {
        $d = CustomerFixtures::officialDraft();
        $a = CustomerFixtures::officialAddress();
        $bp = $d->birthPlace;
        $bank = $d->bankAccount;

        yield 'civility' => [new CustomerDraft('3', $d->birthName, $d->usageName, $d->firstNames, $d->birthDate, $bp, $d->mobilePhone, $d->email, $a, $bank), 'civility: civility.invalid_format'];
        yield 'birth name' => [new CustomerDraft($d->civility, 'Durand2', $d->usageName, $d->firstNames, $d->birthDate, $bp, $d->mobilePhone, $d->email, $a, $bank), 'birthName: birth_name.invalid_characters'];
        yield 'usage name' => [new CustomerDraft($d->civility, $d->birthName, 'Martin-', $d->firstNames, $d->birthDate, $bp, $d->mobilePhone, $d->email, $a, $bank), 'usageName: usage_name.invalid_format'];
        yield 'first names' => [new CustomerDraft($d->civility, $d->birthName, $d->usageName, '', $d->birthDate, $bp, $d->mobilePhone, $d->email, $a, $bank), 'firstNames: first_names.required'];
        yield 'birth date' => [new CustomerDraft($d->civility, $d->birthName, $d->usageName, $d->firstNames, '1980-02-30', $bp, $d->mobilePhone, $d->email, $a, $bank), 'birthDate: calendar_date.invalid_date'];
        yield 'birth place' => [new CustomerDraft($d->civility, $d->birthName, $d->usageName, $d->firstNames, $d->birthDate, new BirthPlaceDraft('99100', '069', '01', 'Lyon'), $d->mobilePhone, $d->email, $a, $bank), 'birthPlace.municipalityCode: birth_municipality_code.invalid_format'];
        yield 'mobile phone' => [new CustomerDraft($d->civility, $d->birthName, $d->usageName, $d->firstNames, $d->birthDate, $bp, '0105040302', $d->email, $a, $bank), 'mobilePhone: mobile_phone_number.invalid_format'];
        yield 'email' => [new CustomerDraft($d->civility, $d->birthName, $d->usageName, $d->firstNames, $d->birthDate, $bp, $d->mobilePhone, 'jeanne', $a, $bank), 'email: email_address.invalid_format'];
        yield 'address' => [new CustomerDraft($d->civility, $d->birthName, $d->usageName, $d->firstNames, $d->birthDate, $bp, $d->mobilePhone, $d->email, new AddressDraft('8', 'B', 'R', 'du Soleil', null, null, 'LYON 01', '69101', '6900', '99100'), $bank), 'address.postalCode: postal_code.invalid_format'];
        yield 'bank account' => [new CustomerDraft($d->civility, $d->birthName, $d->usageName, $d->firstNames, $d->birthDate, $bp, $d->mobilePhone, $d->email, $a, new BankAccountDraft('FR7630006000011234567890188', 'BNPAFRPPXXX', 'Mme Jeanne Martin')), 'bankAccount.iban: iban.invalid_checksum'];
    }

    #[DataProvider('provideSingleViolations')]
    public function testSingleViolation(CustomerDraft $draft, string $expected): void
    {
        static::assertSame([$expected], ViolationSummary::of((new CustomerFactory())->validate($draft)));

        $this->expectException(ValidationFailed::class);

        (new CustomerFactory())->fromDraft($draft);
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function provideOptionalUsageNames(): iterable
    {
        yield 'null' => [null, null];
        yield 'empty string' => ['', null];
        yield 'same as birth name' => ['Durand', null];
        yield 'different' => ['Martin', 'Martin'];
    }

    #[DataProvider('provideOptionalUsageNames')]
    public function testOptionalUsageName(?string $usageName, ?string $expected): void
    {
        $d = CustomerFixtures::officialDraft();
        $customer = (new CustomerFactory())->fromDraft(new CustomerDraft(
            $d->civility,
            $d->birthName,
            $usageName,
            $d->firstNames,
            $d->birthDate,
            $d->birthPlace,
            $d->mobilePhone,
            $d->email,
            $d->address,
            $d->bankAccount,
        ));

        static::assertSame($expected, $customer->civilStatus()->usageName()?->value());
    }
}
