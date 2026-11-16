<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineIntegration;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\AccountHolderType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\AddressComplementType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\AddressMunicipalityNameType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\BicType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\BillingThirdPartyIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\BirthMunicipalityCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\BirthMunicipalityNameType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\CalendarDateType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\CountryCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\DepartmentCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\EmailAddressType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\IbanType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InvoiceNumberType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\LocalityType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\LocalPaymentRequestIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\MobilePhoneNumberType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\MunicipalityCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\PaymentRequestIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\PersonNameType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\PostalCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\RegistrationIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\StreetLetterType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\StreetNameType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\StreetNumberType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\StreetTypeCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\UrssafCustomerIdType;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\AddressText;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetLetter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetTypeCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\AccountHolder;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Bic;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Iban;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\DepartmentCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\PersonName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\BillingThirdPartyId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\MobilePhoneNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use Throwable;

final class StringValueTypesTest extends TestCase
{
    private const UUID = 'af0be430-908a-11eb-a8b3-0242ac130003';

    private AbstractPlatform $platform;

    protected function setUp(): void
    {
        DoctrineIntegration::registerTypes();
        $this->platform = new SQLitePlatform();
    }

    /**
     * @return iterable<string, array{string, string, string}> type name, stored value, an invalid stored value
     */
    public static function types(): iterable
    {
        yield 'registration id' => [RegistrationIdType::NAME, 'reg-1', ''];
        yield 'tenant id' => [TenantIdType::NAME, 'tenant-1', ''];
        yield 'external reference' => [ExternalReferenceType::NAME, 'ref-1', ''];
        yield 'URSSAF customer id' => [UrssafCustomerIdType::NAME, self::UUID, 'not-a-uuid'];
        yield 'invoice number' => [InvoiceNumberType::NAME, 'INV-1', ''];
        yield 'payment request id' => [PaymentRequestIdType::NAME, 'urssaf-1', ''];
        yield 'local payment request id' => [LocalPaymentRequestIdType::NAME, 'local-1', ''];
        yield 'billing third party id' => [BillingThirdPartyIdType::NAME, 'menage.fr', ''];
        yield 'calendar date' => [CalendarDateType::NAME, '2022-02-01', '2022-13-45'];
        yield 'person name' => [PersonNameType::NAME, 'Durand', 'Dur4nd'];
        yield 'IBAN' => [IbanType::NAME, 'FR7630006000011234567890189', 'FR7630006000011234567890188'];
        yield 'BIC' => [BicType::NAME, 'BNPAFRPPXXX', 'BNP'];
        yield 'account holder' => [AccountHolderType::NAME, 'Mme Jeanne Martin', ' '];
        yield 'e-mail address' => [EmailAddressType::NAME, 'jeanne.durand@contact.fr', 'jeanne'];
        yield 'mobile phone number' => [MobilePhoneNumberType::NAME, '0605040302', '0105040302'];
        yield 'street number' => [StreetNumberType::NAME, '8', '0'];
        yield 'street letter' => [StreetLetterType::NAME, 'B', '1'];
        yield 'street type code' => [StreetTypeCodeType::NAME, 'R', 'XX'];
        yield 'street name' => [StreetNameType::NAME, 'du Soleil', str_repeat('a', 29)];
        yield 'address complement' => [AddressComplementType::NAME, 'Batiment A', str_repeat('a', 39)];
        yield 'locality' => [LocalityType::NAME, 'Le Beyssat', str_repeat('a', 39)];
        yield 'address municipality name' => [AddressMunicipalityNameType::NAME, 'LYON 01', 'LYON;01'];
        yield 'birth municipality name' => [BirthMunicipalityNameType::NAME, 'Saint-Etienne (Loire)', 'Lyon;1'];
        yield 'municipality code' => [MunicipalityCodeType::NAME, '69101', '6910'];
        yield 'postal code' => [PostalCodeType::NAME, '69001', '6900'];
        yield 'country code' => [CountryCodeType::NAME, '99100', '991'];
        yield 'department code' => [DepartmentCodeType::NAME, '069', '69'];
        yield 'birth municipality code' => [BirthMunicipalityCodeType::NAME, '101', '1'];
    }

    #[DataProvider('types')]
    public function testItStoresTheValueAndRebuildsTheValueObject(string $name, string $stored): void
    {
        $type = Type::getType($name);
        $value = self::valueObject($name, $stored);

        static::assertSame($stored, $type->convertToDatabaseValue($value, $this->platform));
        static::assertEquals($value, $type->convertToPHPValue($stored, $this->platform));
    }

    #[DataProvider('types')]
    public function testNullStaysNull(string $name): void
    {
        $type = Type::getType($name);

        static::assertNull($type->convertToDatabaseValue(null, $this->platform));
        static::assertNull($type->convertToPHPValue(null, $this->platform));
    }

    #[DataProvider('types')]
    public function testItRefusesAForeignPhpValue(string $name, string $stored): void
    {
        $foreign = new class implements Stringable {
            public function __toString(): string
            {
                return 'foreign';
            }
        };

        try {
            Type::getType($name)->convertToDatabaseValue($foreign, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: ' . self::valueObject($name, $stored)::class . ', null.', $exception->getMessage());
        }
    }

    #[DataProvider('types')]
    public function testItRefusesAStoredValueThatIsNotAString(string $name): void
    {
        try {
            Type::getType($name)->convertToPHPValue(12, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringEndsWith('types: string, null.', $exception->getMessage());
        }
    }

    #[DataProvider('types')]
    public function testItReportsAStoredValueTheValueObjectRefuses(string $name, string $stored, string $invalid): void
    {
        try {
            Type::getType($name)->convertToPHPValue($invalid, $this->platform);
            static::fail('Expected a conversion failure.');
        } catch (ConversionException $exception) {
            static::assertStringContainsString(Type::getType($name)::class, $exception->getMessage());
            static::assertInstanceOf(Throwable::class, $exception->getPrevious());
        }
    }

    /**
     * @throws InvalidValue
     */
    private static function valueObject(string $typeName, string $stored): object
    {
        return match ($typeName) {
            RegistrationIdType::NAME => RegistrationId::fromString($stored),
            TenantIdType::NAME => TenantId::fromString($stored),
            ExternalReferenceType::NAME => ExternalReference::fromString($stored),
            UrssafCustomerIdType::NAME => UrssafCustomerId::fromString($stored),
            InvoiceNumberType::NAME => InvoiceNumber::fromString($stored),
            PaymentRequestIdType::NAME => PaymentRequestId::fromString($stored),
            LocalPaymentRequestIdType::NAME => LocalPaymentRequestId::fromString($stored),
            BillingThirdPartyIdType::NAME => BillingThirdPartyId::fromString($stored),
            PersonNameType::NAME => PersonName::birthName($stored),
            IbanType::NAME => Iban::fromString($stored),
            BicType::NAME => Bic::fromString($stored),
            AccountHolderType::NAME => AccountHolder::fromString($stored),
            EmailAddressType::NAME => EmailAddress::fromString($stored),
            MobilePhoneNumberType::NAME => MobilePhoneNumber::fromString($stored),
            StreetNumberType::NAME => StreetNumber::fromString($stored),
            StreetLetterType::NAME => StreetLetter::fromString($stored),
            StreetTypeCodeType::NAME => StreetTypeCode::fromString($stored),
            StreetNameType::NAME => AddressText::streetName($stored),
            AddressComplementType::NAME => AddressText::complement($stored),
            LocalityType::NAME => AddressText::locality($stored),
            AddressMunicipalityNameType::NAME => MunicipalityName::ofAddress($stored),
            BirthMunicipalityNameType::NAME => MunicipalityName::ofBirthPlace($stored),
            MunicipalityCodeType::NAME => MunicipalityCode::fromString($stored),
            PostalCodeType::NAME => PostalCode::fromString($stored),
            CountryCodeType::NAME => CountryCode::fromString($stored),
            DepartmentCodeType::NAME => DepartmentCode::fromString($stored),
            BirthMunicipalityCodeType::NAME => BirthMunicipalityCode::fromString($stored),
            default => CalendarDate::fromString($stored),
        };
    }

    public function testStringTypesUseTheColumnLengthAndDatesUseTheDateType(): void
    {
        static::assertSame('VARCHAR(64)', Type::getType(TenantIdType::NAME)->getSQLDeclaration(['length' => 64], $this->platform));
        static::assertSame('DATE', Type::getType(CalendarDateType::NAME)->getSQLDeclaration([], $this->platform));
    }
}
