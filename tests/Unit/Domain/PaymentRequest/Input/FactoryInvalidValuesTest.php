<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\DepositDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\LineAmountsDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PeriodDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\ServiceLineDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification\ViolationAssertions;

final class FactoryInvalidValuesTest extends TestCase
{
    public function testInvalidInvoiceValues(): void
    {
        $draft = new PaymentRequestDraft(
            str_repeat('x', 192),
            str_repeat('x', 51),
            '2022-02-30',
            new PeriodDraft('2022-02-01', '2022-02-01T16:45:00+01:00'),
            '11.025',
            '-1',
            [],
            new DepositDraft('abc', '01/02/2022'),
        );

        static::assertSame(
            [
                'error externalReference external_reference.too_long',
                'error invoiceNumber invoice_number.too_long',
                'error invoiceDate calendar_date.invalid_date',
                'error employmentPeriod.start instant.invalid_format',
                'error totalInclTax money.too_many_decimals',
                'error totalExclTax money.negative',
                'error deposit.amount money.invalid_format',
                'error deposit.paymentDate calendar_date.invalid_format',
            ],
            ViolationAssertions::summary(self::validate($draft)),
        );
    }

    public function testInvalidPeriodAndDeposit(): void
    {
        $draft = new PaymentRequestDraft(
            'ref',
            '1',
            '2022-02-01',
            new PeriodDraft('2022-02-01T16:45:00Z', '2022-02-01T16:40:00Z'),
            '1',
            '1',
            [],
            new DepositDraft('0', '2022-02-01'),
        );

        static::assertSame(
            ['error employmentPeriod employment_period.end_before_start', 'error deposit.amount deposit.not_positive'],
            ViolationAssertions::summary(self::validate($draft)),
        );
    }

    public function testInvalidLineValues(): void
    {
        $line = new ServiceLineDraft(
            '15',
            '30A009',
            'hour',
            '0',
            '5.5155',
            new LineAmountsDraft('5.511', '5,01', '-0.5'),
            'SAP12345678',
            new PeriodDraft('2022-01-31T22:00:00Z', '2022-02-01T10:00:00Z'),
            str_repeat('x', 256),
        );

        static::assertSame(
            [
                'error lines[0].natureCode nature_code.unknown',
                'error lines[0].activityCode activity_code.unknown',
                'error lines[0].unit unit.unknown',
                'error lines[0].quantity quantity.not_positive',
                'error lines[0].unitPrice unit_price.too_many_decimals',
                'error lines[0].amounts.totalInclTax money.too_many_decimals',
                'error lines[0].amounts.totalExclTax money.invalid_format',
                'error lines[0].amounts.vatAmount money.negative',
                'error lines[0].intervenantId intervenant_id.invalid_format',
                'error lines[0].period employment_period.not_single_month',
                'error lines[0].complement line_complement.too_long',
            ],
            ViolationAssertions::summary(self::validate(DraftFixtures::draft([$line], null))),
        );
    }

    public function testEachLineIsReportedAtItsIndex(): void
    {
        $invalid = new ServiceLineDraft('60', null, 'HEURE', '1', '5.51', null, 'SAP123456789', null, null);
        $draft = DraftFixtures::draft([DraftFixtures::firstLine(), $invalid, DraftFixtures::secondLine(), $invalid], null);

        static::assertSame(
            ['error lines[1].amounts field.required', 'error lines[3].amounts field.required'],
            ViolationAssertions::summary(self::validate($draft)),
        );
    }

    private static function validate(PaymentRequestDraft $draft): ValidationResult
    {
        return (new PaymentRequestFactory())->validate($draft, Fixtures::context(Fixtures::NOW, Fixtures::PAIRING));
    }
}
