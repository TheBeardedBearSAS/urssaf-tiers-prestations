<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Customer;

final class CustomerTest extends TestCase
{
    /**
     * The official request example of method 010 builds successfully and every part is preserved.
     */
    public function testOfficialExample(): void
    {
        $customer = CustomerFixtures::customer();

        $civil = $customer->civilStatus();
        static::assertSame('1', $civil->civility()->value);
        static::assertSame('Durand', $civil->birthName()->value());
        static::assertSame('Martin', $civil->usageName()?->value());
        static::assertSame('Eric-Antoine', $civil->firstNames()->value());
        static::assertSame('1980-03-29', $civil->birthDate()->toString());
        static::assertSame('99100', $civil->birthPlace()->country()->value());
        static::assertSame('069', $civil->birthPlace()->department()?->value());
        $municipality = $civil->birthPlace()->municipality();
        static::assertNotNull($municipality);
        static::assertSame('001', $municipality->code()->value());
        static::assertSame('Lyon', $municipality->name()->value());

        $contact = $customer->contactDetails();
        static::assertSame('0605040302', $contact->mobilePhone()->value());
        static::assertSame('jeanne.durand@contact.fr', $contact->email()->value());
        $address = $contact->address();
        static::assertSame('8', $address->street()->number()?->value());
        static::assertSame('B', $address->street()->letter()?->value());
        static::assertSame('R', $address->street()->typeCode()?->value());
        static::assertSame('du Soleil', $address->street()->name()?->value());
        static::assertSame('Batiment A', $address->street()->complement()?->value());
        static::assertSame('Le Beyssat', $address->street()->locality()?->value());
        static::assertSame('LYON 01', $address->municipalityName()->value());
        static::assertSame('69101', $address->municipalityCode()->value());
        static::assertSame('69001', $address->postalCode()->value());
        static::assertSame('99100', $address->countryCode()->value());

        $bank = $customer->bankAccount();
        static::assertSame('BNPAFRPPXXX', $bank->bic()->value());
        static::assertSame('FR7630006000011234567890189', $bank->iban()->value());
        static::assertSame('Mme Jeanne Martin', $bank->holder()->value());
    }

    public function testOf(): void
    {
        $customer = CustomerFixtures::customer();
        $copy = Customer::of($customer->civilStatus(), $customer->contactDetails(), $customer->bankAccount());

        static::assertSame($customer->civilStatus(), $copy->civilStatus());
        static::assertSame($customer->contactDetails(), $copy->contactDetails());
        static::assertSame($customer->bankAccount(), $copy->bankAccount());
    }
}
