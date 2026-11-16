<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use ReflectionClass;
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
     * Whether native lazy objects (PHP 8.4 lazy objects, used instead of generated proxies) can be enabled: they need
     * PHP 8.4 and an ORM that implements them (3.4+).
     */
    public static function nativeLazyObjectsAvailable(): bool
    {
        return self::runtimeNativeLazyObjectsOptions() !== [];
    }

    /**
     * The "orm" options that enable the native lazy objects, or none when the runtime cannot use them.
     *
     * @param class-string $ormConfiguration the ORM configuration class, which has "enableNativeLazyObjects()" from ORM 3.4
     *
     * @return array{enable_native_lazy_objects?: true}
     */
    public static function nativeLazyObjectsOptions(int $phpVersionId, string $ormConfiguration): array
    {
        if ($phpVersionId >= 80_400 && (new ReflectionClass($ormConfiguration))->hasMethod('enableNativeLazyObjects')) {
            return ['enable_native_lazy_objects' => true];
        }

        return [];
    }

    /**
     * Configuration to prepend to the "doctrine" extension of doctrine-bundle: the types, the XML mapping of the
     * default entity manager (or of the entity manager named by the host, under "orm.entity_managers.<name>.mappings")
     * and, when PHP and the ORM support them, the native lazy objects (symfony/var-exporter dropped the lazy ghosts the
     * ORM proxies rely on in version 8). The host can still override it in its own "doctrine" configuration.
     *
     * @return array{
     *     dbal: array{types: array<non-empty-string, class-string<Type>>},
     *     orm: array{enable_native_lazy_objects?: true, mappings: array<string, array{type: string, is_bundle: bool, dir: string, prefix: string, alias: string}>}
     * }
     */
    public static function doctrineBundleConfiguration(): array
    {
        return self::doctrineBundleConfigurationWith(self::runtimeNativeLazyObjectsOptions());
    }

    /**
     * The configuration to prepend, with the given options on top of the "orm" section.
     *
     * @param array{enable_native_lazy_objects?: true} $ormOptions
     *
     * @return array{
     *     dbal: array{types: array<non-empty-string, class-string<Type>>},
     *     orm: array{enable_native_lazy_objects?: true, mappings: array<string, array{type: string, is_bundle: bool, dir: string, prefix: string, alias: string}>}
     * }
     */
    public static function doctrineBundleConfigurationWith(array $ormOptions): array
    {
        return [
            'dbal' => ['types' => self::types()],
            'orm' => $ormOptions + [
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

    /**
     * @return array{enable_native_lazy_objects?: true}
     */
    private static function runtimeNativeLazyObjectsOptions(): array
    {
        return self::nativeLazyObjectsOptions(\PHP_VERSION_ID, Configuration::class);
    }
}
