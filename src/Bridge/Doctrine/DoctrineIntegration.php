<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
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
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\DepositType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\EmailAddressType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\IbanType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InvoiceNumberType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\LocalityType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\LocalPaymentRequestIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\MobilePhoneNumberType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\MoneyType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\MunicipalityCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\PaymentRequestIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\PersonNameType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\PostalCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\RegistrationIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\RejectionCausesType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\ServiceLinesType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\StreetLetterType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\StreetNameType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\StreetNumberType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\StreetTypeCodeType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\SubmissionErrorsType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\TimeZoneType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\UrssafCustomerIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\UrssafTrackingType;

/**
 * Everything a host application needs to persist the two aggregates with Doctrine ORM 3 / DBAL 4.
 *
 * The domain model carries no Doctrine metadata: the mapping lives in XML files (Resources/mapping) whose names follow
 * the Symfony "simplified" convention for the namespace prefix {@see self::MAPPING_NAMESPACE}. A plain application does
 *
 *     DoctrineIntegration::registerTypes();
 *     $configuration->setMetadataDriverImpl(DoctrineIntegration::mappingDriver());
 *
 * and a Symfony application prepends {@see self::doctrineBundleConfiguration()} to the "doctrine" extension.
 */
final class DoctrineIntegration
{
    /**
     * Namespace prefix of the mapped classes (Domain and Bridge entities): file "Domain.Customer.Customer.orm.xml" maps
     * "<prefix>\Domain\Customer\Customer".
     */
    public const MAPPING_NAMESPACE = 'TheBeardedBear\UrssafTiersPrestations';

    public const MAPPING_ALIAS = 'UrssafTiersPrestations';

    /**
     * Prefix of the tables created by the mapping.
     */
    public const TABLE_PREFIX = 'urssaf_tps_';

    public static function mappingDirectory(): string
    {
        return __DIR__ . '/Resources/mapping';
    }

    /**
     * Metadata driver for the mapping files, for applications that configure the ORM without the Symfony bundle.
     */
    public static function mappingDriver(): SimplifiedXmlDriver
    {
        return new SimplifiedXmlDriver([self::mappingDirectory() => self::MAPPING_NAMESPACE]);
    }

    /**
     * The custom DBAL types of the mapping, by registration name.
     *
     * @return array<non-empty-string, class-string<Type>>
     */
    public static function types(): array
    {
        return [
            RegistrationIdType::NAME => RegistrationIdType::class,
            TenantIdType::NAME => TenantIdType::class,
            ExternalReferenceType::NAME => ExternalReferenceType::class,
            UrssafCustomerIdType::NAME => UrssafCustomerIdType::class,
            InvoiceNumberType::NAME => InvoiceNumberType::class,
            PaymentRequestIdType::NAME => PaymentRequestIdType::class,
            LocalPaymentRequestIdType::NAME => LocalPaymentRequestIdType::class,
            BillingThirdPartyIdType::NAME => BillingThirdPartyIdType::class,
            CalendarDateType::NAME => CalendarDateType::class,
            InstantType::NAME => InstantType::class,
            MoneyType::NAME => MoneyType::class,
            TimeZoneType::NAME => TimeZoneType::class,
            ServiceLinesType::NAME => ServiceLinesType::class,
            DepositType::NAME => DepositType::class,
            SubmissionErrorsType::NAME => SubmissionErrorsType::class,
            UrssafTrackingType::NAME => UrssafTrackingType::class,
            PersonNameType::NAME => PersonNameType::class,
            IbanType::NAME => IbanType::class,
            BicType::NAME => BicType::class,
            AccountHolderType::NAME => AccountHolderType::class,
            EmailAddressType::NAME => EmailAddressType::class,
            MobilePhoneNumberType::NAME => MobilePhoneNumberType::class,
            StreetNumberType::NAME => StreetNumberType::class,
            StreetLetterType::NAME => StreetLetterType::class,
            StreetTypeCodeType::NAME => StreetTypeCodeType::class,
            StreetNameType::NAME => StreetNameType::class,
            AddressComplementType::NAME => AddressComplementType::class,
            LocalityType::NAME => LocalityType::class,
            AddressMunicipalityNameType::NAME => AddressMunicipalityNameType::class,
            BirthMunicipalityNameType::NAME => BirthMunicipalityNameType::class,
            MunicipalityCodeType::NAME => MunicipalityCodeType::class,
            PostalCodeType::NAME => PostalCodeType::class,
            CountryCodeType::NAME => CountryCodeType::class,
            DepartmentCodeType::NAME => DepartmentCodeType::class,
            BirthMunicipalityCodeType::NAME => BirthMunicipalityCodeType::class,
            RejectionCausesType::NAME => RejectionCausesType::class,
        ];
    }

    /**
     * Registers the custom types in the global DBAL registry. Idempotent.
     */
    public static function registerTypes(): void
    {
        foreach (self::types() as $name => $class) {
            if (Type::hasType($name)) {
                continue;
            }
            Type::addType($name, $class);
        }
    }

    /**
     * Configuration to prepend to the "doctrine" extension of doctrine-bundle: the types, and the XML mapping of the
     * default entity manager (or of the entity manager named by the host, under "orm.entity_managers.<name>.mappings").
     *
     * @return array{
     *     dbal: array{types: array<non-empty-string, class-string<Type>>},
     *     orm: array{mappings: array<string, array{type: string, is_bundle: bool, dir: string, prefix: string, alias: string}>}
     * }
     */
    public static function doctrineBundleConfiguration(): array
    {
        return [
            'dbal' => ['types' => self::types()],
            'orm' => [
                'mappings' => [
                    self::MAPPING_ALIAS => [
                        'type' => 'xml',
                        'is_bundle' => false,
                        'dir' => self::mappingDirectory(),
                        'prefix' => self::MAPPING_NAMESPACE,
                        'alias' => self::MAPPING_ALIAS,
                    ],
                ],
            ],
        ];
    }
}
