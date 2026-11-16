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

final class FactoryRequiredFieldsTest extends TestCase
{
    public function testEmptyDraft(): void
    {
        $draft = new PaymentRequestDraft(null, null, null, null, null, null, [], null);

        static::assertSame(
            [
                'error externalReference field.required',
                'error invoiceNumber field.required',
                'error invoiceDate field.required',
                'error employmentPeriod field.required',
                'error totalInclTax field.required',
                'error totalExclTax field.required',
            ],
            ViolationAssertions::summary(self::validate($draft)),
        );
    }

    public function testEmptyParts(): void
    {
        $draft = new PaymentRequestDraft(
            'ref',
            '1',
            '2022-02-01',
            new PeriodDraft(null, null),
            '1',
            '1',
            [DraftFixtures::firstLine()],
            new DepositDraft(null, null),
        );

        static::assertSame(
            [
                'error employmentPeriod.start field.required',
                'error employmentPeriod.end field.required',
                'error deposit.amount field.required',
                'error deposit.paymentDate field.required',
            ],
            ViolationAssertions::summary(self::validate($draft)),
        );
    }

    public function testEmptyLine(): void
    {
        $draft = DraftFixtures::draft([new ServiceLineDraft(null, null, null, null, null, null, null, null, null)], null);

        static::assertSame(
            [
                'error lines[0].natureCode field.required',
                'error lines[0].unit field.required',
                'error lines[0].quantity field.required',
                'error lines[0].unitPrice field.required',
                'error lines[0].amounts field.required',
                'error lines[0].intervenantId field.required',
            ],
            ViolationAssertions::summary(self::validate($draft)),
        );
    }

    public function testEmptyLineParts(): void
    {
        $line = new ServiceLineDraft(
            '60',
            null,
            'HEURE',
            '1',
            '5.51',
            new LineAmountsDraft(null, null, null),
            'SAP123456789',
            new PeriodDraft(Fixtures::START, null),
            null,
        );

        static::assertSame(
            [
                'error lines[0].amounts.totalInclTax field.required',
                'error lines[0].amounts.totalExclTax field.required',
                'error lines[0].amounts.vatAmount field.required',
                'error lines[0].period.end field.required',
            ],
            ViolationAssertions::summary(self::validate(DraftFixtures::draft([$line], null))),
        );
    }

    public function testBlankOptionalValuesAreIgnored(): void
    {
        $official = DraftFixtures::firstLine();
        $line = new ServiceLineDraft('60', ' ', 'HEURE', '1', '5.51', $official->amounts, 'SAP123456789', null, '');
        $other = new ServiceLineDraft('60', '', 'HEURE', '1', '5.51', $official->amounts, 'SAP123456789', null, '  ');

        static::assertSame([], self::validate(DraftFixtures::draft([$line, $other], null))->all());
    }

    private static function validate(PaymentRequestDraft $draft): ValidationResult
    {
        return (new PaymentRequestFactory())->validate($draft, Fixtures::context(Fixtures::NOW, Fixtures::PAIRING));
    }
}
