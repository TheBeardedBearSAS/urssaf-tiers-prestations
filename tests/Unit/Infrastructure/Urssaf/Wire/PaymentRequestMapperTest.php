<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestMapper;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures as DomainFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input\DraftFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

/**
 * Golden tests of the method 050 body.
 *
 * Decimals: money is encoded with 2 decimals ("0.50") and unit prices/quantities with their own scale ("5.51", "1").
 * "0.50" is a valid JSON number equal to the official "0.5", so the golden comparison decodes both documents and
 * compares numbers by value; the byte-level tests below pin the exact literals (no float drift).
 */
final class PaymentRequestMapperTest extends TestCase
{
    private function official(): PaymentRequest
    {
        return (new PaymentRequestFactory())->fromDraft(
            LocalPaymentRequestId::fromString('local-1'),
            DraftFixtures::official(),
            DomainFixtures::context(DomainFixtures::NOW, DomainFixtures::PAIRING),
        );
    }

    public function testItEncodesTheOfficialExampleLikeTheGoldenFixture(): void
    {
        $body = (new PaymentRequestMapper())->toBody($this->official());

        static::assertSame(
            json_decode(Fixtures::load('050-submit-request'), true, 512, JSON_THROW_ON_ERROR),
            json_decode(JsonEncoder::encode($body), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testAmountsAreExactDecimalLiteralsWithoutFloatDrift(): void
    {
        $json = JsonEncoder::encode((new PaymentRequestMapper())->toBody($this->official()));

        static::assertStringContainsString('"mntFactureTTC":11.02,"mntFactureHT":10.02', $json);
        static::assertStringContainsString('"mntAcompte":2.75,', $json);
        static::assertStringContainsString('"quantite":1,"unite":"HEURE","mntUnitaireTTC":5.51,"mntPrestationTTC":5.51', $json);
        static::assertStringContainsString('"mntPrestationHT":5.01,"mntPrestationTVA":0.50,', $json);
    }

    public function testUnitPriceAndQuantityKeepTheirDecimals(): void
    {
        $line = DomainFixtures::line('1.75', '5.515', '9.65', '8.77', '0.88');
        $request = PaymentRequest::create(
            LocalPaymentRequestId::fromString('local-2'),
            TenantId::fromString('tenant-1'),
            ExternalReference::fromString('invoice-43'),
            DomainFixtures::invoice(ServiceLines::of($line), '9.65', '8.77', null),
            DomainFixtures::at(DomainFixtures::NOW),
        );

        $json = JsonEncoder::encode((new PaymentRequestMapper())->toBody($request));

        static::assertStringContainsString('"quantite":1.75,"unite":"HEURE","mntUnitaireTTC":5.515,"mntPrestationTTC":9.65', $json);
    }

    public function testOptionalFieldsAreOmittedWhenAbsent(): void
    {
        $body = (new PaymentRequestMapper())->toBody(DomainFixtures::pendingRequest());

        static::assertCount(1, $body);
        static::assertArrayNotHasKey('mntAcompte', $body[0]);
        static::assertArrayNotHasKey('dateVersementAcompte', $body[0]);
        static::assertStringContainsString(
            '"inputPrestations":[{"codeNature":"60","quantite":1,"unite":"HEURE","mntUnitaireTTC":5.51,'
            . '"mntPrestationTTC":5.51,"mntPrestationHT":5.01,"mntPrestationTVA":0.50,"complement2":"SAP123456789"},',
            JsonEncoder::encode($body),
        );
        static::assertSame(
            [
                'idTiersFacturation',
                'idClient',
                'dateNaissanceClient',
                'numFactureTiers',
                'dateFacture',
                'dateDebutEmploi',
                'dateFinEmploi',
                'mntFactureTTC',
                'mntFactureHT',
                'inputPrestations',
            ],
            array_keys($body[0]),
        );
    }

    public function testItNeverSendsInternalIdentifiers(): void
    {
        $json = JsonEncoder::encode((new PaymentRequestMapper())->toBody($this->official()));

        static::assertStringNotContainsString('local-1', $json);
        static::assertStringNotContainsString('tenant-1', $json);
        static::assertStringNotContainsString('invoice-42', $json);
    }

    public function testItEncodesSeveralRequestsInOrderAsAList(): void
    {
        $body = (new PaymentRequestMapper())->toBody($this->official(), DomainFixtures::pendingRequest());

        static::assertSame([0, 1], array_keys($body));
        static::assertSame('2022-AZ-00001', $body[0]['numFactureTiers']);
        static::assertArrayHasKey('mntAcompte', $body[0]);
        static::assertArrayNotHasKey('mntAcompte', $body[1]);
    }

    public function testNoRequestGivesAnEmptyList(): void
    {
        static::assertSame([], (new PaymentRequestMapper())->toBody());
    }
}
