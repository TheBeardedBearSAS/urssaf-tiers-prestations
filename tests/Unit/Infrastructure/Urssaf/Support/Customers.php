<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\AddressDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

final class Customers
{
    private function __construct() {}

    public static function draft(?BirthPlaceDraft $birthPlace, AddressDraft $address, ?string $usageName): CustomerDraft
    {
        $official = CustomerFixtures::officialDraft();

        return new CustomerDraft(
            $official->civility,
            $official->birthName,
            $usageName,
            $official->firstNames,
            $official->birthDate,
            $birthPlace,
            $official->mobilePhone,
            $official->email,
            $address,
            $official->bankAccount,
        );
    }

    public static function registration(CustomerDraft $draft): CustomerRegistration
    {
        return CustomerRegistration::request(
            RegistrationId::fromString('reg-1'),
            TenantId::fromString('tenant-1'),
            ExternalReference::fromString('customer-42'),
            (new CustomerFactory())->fromDraft($draft),
            CustomerFixtures::at('2026-11-10T09:00:00Z'),
        );
    }
}
