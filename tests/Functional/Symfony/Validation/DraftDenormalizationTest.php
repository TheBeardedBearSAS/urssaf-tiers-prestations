<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\AddressDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BankAccountDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\DepositDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\LineAmountsDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PeriodDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\ServiceLineDraft;

/**
 * The drafts are plain readonly objects: the Symfony Serializer builds them from a request body, as documented in
 * docs/symfony.md (validation and forms).
 */
final class DraftDenormalizationTest extends TestCase
{
    public function testAPaymentRequestDraftIsBuiltFromJsonWithItsNestedLines(): void
    {
        $json = <<<'JSON'
            {
                "externalReference": "invoice-42",
                "invoiceNumber": "2022-AZ-00001",
                "invoiceDate": "2022-02-01",
                "employmentPeriod": {"start": "2022-01-01T08:00:00Z", "end": "2022-01-31T18:00:00Z"},
                "totalInclTax": "5.51",
                "totalExclTax": "5.01",
                "lines": [
                    {
                        "natureCode": "60",
                        "unit": "HEURE",
                        "quantity": "1",
                        "unitPrice": "5.51",
                        "amounts": {"totalInclTax": "5.51", "totalExclTax": "5.01", "vatAmount": "0.5"},
                        "intervenantId": "SAP123456789"
                    }
                ],
                "deposit": {"amount": "2.75", "paymentDate": "2022-02-01"}
            }
            JSON;

        $draft = self::serializer()->deserialize($json, PaymentRequestDraft::class, 'json');

        static::assertEquals(new PaymentRequestDraft(
            'invoice-42',
            '2022-AZ-00001',
            '2022-02-01',
            new PeriodDraft('2022-01-01T08:00:00Z', '2022-01-31T18:00:00Z'),
            '5.51',
            '5.01',
            [new ServiceLineDraft('60', null, 'HEURE', '1', '5.51', new LineAmountsDraft('5.51', '5.01', '0.5'), 'SAP123456789', null, null)],
            new DepositDraft('2.75', '2022-02-01'),
        ), $draft);
    }

    public function testACustomerDraftIsBuiltFromJsonAndMissingFieldsAreNull(): void
    {
        $json = <<<'JSON'
            {
                "civility": "2",
                "birthName": "Durand",
                "firstNames": "Jeanne",
                "birthDate": "1980-05-17",
                "birthPlace": {"countryCode": "99100", "departmentCode": "075", "municipalityCode": "056", "municipalityName": "Paris"},
                "email": "jeanne.durand@example.com",
                "address": {"streetNumber": "8", "streetTypeCode": "R", "streetName": "du Moulin", "municipalityName": "Paris", "municipalityCode": "75056", "postalCode": "75001", "countryCode": "99100"},
                "bankAccount": {"iban": "FR7630006000011234567890189", "bic": "AGRIFRPP", "holder": "Jeanne Durand"}
            }
            JSON;

        $draft = self::serializer()->deserialize($json, CustomerDraft::class, 'json');

        static::assertEquals(new CustomerDraft(
            '2',
            'Durand',
            null,
            'Jeanne',
            '1980-05-17',
            new BirthPlaceDraft('99100', '075', '056', 'Paris'),
            null,
            'jeanne.durand@example.com',
            new AddressDraft('8', null, 'R', 'du Moulin', null, null, 'Paris', '75056', '75001', '99100'),
            new BankAccountDraft('FR7630006000011234567890189', 'AGRIFRPP', 'Jeanne Durand'),
        ), $draft);
    }

    private static function serializer(): Serializer
    {
        $reflection = new ReflectionExtractor();
        $types = new PropertyInfoExtractor([$reflection], [new PhpStanExtractor(), $reflection]);

        return new Serializer([new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, $types)], [new JsonEncoder()]);
    }
}
