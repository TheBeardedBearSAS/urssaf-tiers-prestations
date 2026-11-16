<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\AddressText;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\AccountHolder;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\BankAccount;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Bic;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Iban;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthPlace;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\Civility;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\CivilStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\PersonName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\MobilePhoneNumber;

/**
 * Identity snapshot of a customer as sent to URSSAF on registration (method 010, InputParticulierDTO).
 *
 * Build it from validated value objects, or from raw input with {@see Input\CustomerFactory}.
 */
final readonly class Customer
{
    /**
     * Text replacing every free-text personal value of an anonymised customer.
     */
    public const ANONYMISED = 'ANONYMISED';

    private function __construct(
        private CivilStatus $civilStatus,
        private ContactDetails $contactDetails,
        private BankAccount $bankAccount,
    ) {}

    public static function of(CivilStatus $civilStatus, ContactDetails $contactDetails, BankAccount $bankAccount): self
    {
        return new self($civilStatus, $contactDetails, $bankAccount);
    }

    /**
     * The fixed customer that replaces the personal data of an anonymised registration (GDPR erasure): every value is
     * valid but identifies nobody (names and texts "ANONYMISED", birth date 1900-01-01 abroad in unknown country
     * 99999, phone 0600000000, e-mail anonymised@example.invalid, address "ANONYMISED" 00000, all-zero IBAN with valid
     * check digits, BIC XXXXXXXX). It is never sent to URSSAF.
     */
    public static function anonymised(): self
    {
        return new self(
            new CivilStatus(
                Civility::Male,
                PersonName::birthName(self::ANONYMISED),
                null,
                PersonName::firstNames(self::ANONYMISED),
                CalendarDate::fromString('1900-01-01'),
                BirthPlace::abroad(CountryCode::fromString('99999')),
            ),
            ContactDetails::of(
                MobilePhoneNumber::fromString('0600000000'),
                EmailAddress::fromString('anonymised@example.invalid'),
                PostalAddress::of(
                    new StreetAddress(null, null, null, null, null, AddressText::locality(self::ANONYMISED)),
                    MunicipalityName::ofAddress(self::ANONYMISED),
                    MunicipalityCode::fromString('00000'),
                    PostalCode::fromString('00000'),
                    CountryCode::france(),
                ),
            ),
            BankAccount::of(
                Iban::fromString('FR7600000000000000000000000'),
                Bic::fromString('XXXXXXXX'),
                AccountHolder::fromString(self::ANONYMISED),
            ),
        );
    }

    public function civilStatus(): CivilStatus
    {
        return $this->civilStatus;
    }

    public function contactDetails(): ContactDetails
    {
        return $this->contactDetails;
    }

    public function bankAccount(): BankAccount
    {
        return $this->bankAccount;
    }

    public function equals(self $other): bool
    {
        return $this->civilStatus->equals($other->civilStatus)
            && $this->contactDetails->equals($other->contactDetails)
            && $this->bankAccount->equals($other->bankAccount);
    }
}
