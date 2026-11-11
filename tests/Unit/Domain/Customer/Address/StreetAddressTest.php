<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Address;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\AddressText;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetLetter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetTypeCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;

final class StreetAddressTest extends TestCase
{
    public function testFullStreetAddress(): void
    {
        $street = new StreetAddress(
            StreetNumber::fromString('8'),
            StreetLetter::fromString('B'),
            StreetTypeCode::fromString('R'),
            AddressText::streetName('du Soleil'),
            AddressText::complement('Batiment A'),
            AddressText::locality('Le Beyssat'),
        );

        static::assertSame('8', $street->number()?->value());
        static::assertSame('B', $street->letter()?->value());
        static::assertSame('R', $street->typeCode()?->value());
        static::assertSame('du Soleil', $street->name()?->value());
        static::assertSame('Batiment A', $street->complement()?->value());
        static::assertSame('Le Beyssat', $street->locality()?->value());
    }

    public function testMinimalStreetAddress(): void
    {
        $street = new StreetAddress(null, null, null, null, null, AddressText::locality('Le Beyssat'));

        static::assertNull($street->number());
        static::assertNull($street->letter());
        static::assertNull($street->typeCode());
        static::assertNull($street->name());
        static::assertNull($street->complement());
        static::assertSame('Le Beyssat', $street->locality()?->value());
        static::assertSame('Batiment A', (new StreetAddress(null, null, null, null, AddressText::complement('Batiment A'), null))->complement()?->value());
    }

    public function testFirstBrokenRuleIsThrown(): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'street_number.required_with_street_letter',
            'A street number is required when a street letter is given.',
        ));

        new StreetAddress(null, StreetLetter::fromString('B'), null, null, null, null);
    }

    public function testDeliveryPointIsRequired(): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'postal_address.delivery_point_required',
            'At least one of the street name, the complement or the locality is required.',
        ));

        new StreetAddress(null, null, null, null, null, null);
    }

    public function testStreetNameRequiresType(): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'street_type_code.required_with_street_name',
            'A street type code is required when a street name is given.',
        ));

        new StreetAddress(StreetNumber::fromString('8'), null, null, AddressText::streetName('du Soleil'), null, null);
    }

    public function testPostalAddress(): void
    {
        $street = new StreetAddress(null, null, null, null, null, AddressText::locality('Le Beyssat'));
        $address = PostalAddress::of(
            $street,
            MunicipalityName::ofAddress('LYON 01'),
            MunicipalityCode::fromString('69101'),
            PostalCode::fromString('69001'),
            CountryCode::france(),
        );

        static::assertSame($street, $address->street());
        static::assertSame('LYON 01', $address->municipalityName()->value());
        static::assertSame('69101', $address->municipalityCode()->value());
        static::assertSame('69001', $address->postalCode()->value());
        static::assertSame('99100', $address->countryCode()->value());
    }
}
