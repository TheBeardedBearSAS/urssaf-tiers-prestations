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
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\DepositDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\LineAmountsDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PeriodDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\ServiceLineDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestContext;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification\ViolationAssertions;

final class FactoryRulesTest extends TestCase
{
    public function testLineRulesAreReportedEvenWhenTheInvoiceIsIncomplete(): void
    {
        $line = new ServiceLineDraft('270', '60A001', 'HEURE', '2', '5.51', new LineAmountsDraft('5.51', '1', '0'), 'SAP123456789', null, null);
        $draft = new PaymentRequestDraft('ref', null, '2022-02-01', new PeriodDraft(Fixtures::START, Fixtures::END), '1', '1', [$line], null);

        static::assertSame(
            [
                'error invoiceNumber field.required',
                'error lines[0].amounts.vatAmount line.vat_mismatch',
                'error lines[0].amounts.totalInclTax line.total_mismatch',
                'error lines[0].activityCode line.activity_not_in_nature',
                'warning lines[0].natureCode line.not_eligible',
            ],
            ViolationAssertions::summary(self::validate($draft, self::context())),
        );
    }

    public function testInvoiceAndTimeRulesOnACompleteInvoice(): void
    {
        $draft = DraftFixtures::draft([], new DepositDraft('2.75', '2022-02-01'));
        $context = Fixtures::context('2022-02-01T16:44:00Z', '2022-02-01T16:41:00Z');

        static::assertSame(
            [
                'error lines lines.empty',
                'error employmentPeriod employment_period.in_future',
                'error employmentPeriod employment_period.before_pairing',
            ],
            ViolationAssertions::summary(self::validate($draft, $context)),
        );
    }

    public function testInvoiceRulesRunDespiteAnInvalidReferenceOrDeposit(): void
    {
        $official = DraftFixtures::official();
        $draft = new PaymentRequestDraft(
            '',
            $official->invoiceNumber,
            $official->invoiceDate,
            $official->employmentPeriod,
            '20',
            $official->totalExclTax,
            $official->lines,
            new DepositDraft('x', '2022-02-01'),
        );

        static::assertSame(
            [
                'error externalReference external_reference.empty',
                'error deposit.amount money.invalid_format',
                'error totalInclTax invoice.total_mismatch',
            ],
            ViolationAssertions::summary(self::validate($draft, self::context())),
        );
    }

    public function testFromDraftReportsEveryViolation(): void
    {
        $context = Fixtures::context('2022-02-01T16:44:00Z', Fixtures::PAIRING);

        try {
            (new PaymentRequestFactory())->fromDraft(LocalPaymentRequestId::fromString('id'), DraftFixtures::official(), $context);
            static::fail('Expected exception');
        } catch (ValidationFailed $exception) {
            static::assertSame(
                ['error employmentPeriod employment_period.in_future'],
                ViolationAssertions::summary($exception->result()),
            );
        }
    }

    public function testFromDraftRefusesAnIncompleteDraft(): void
    {
        $draft = new PaymentRequestDraft(null, null, null, null, null, null, [], null);

        try {
            (new PaymentRequestFactory())->fromDraft(LocalPaymentRequestId::fromString('id'), $draft, self::context());
            static::fail('Expected exception');
        } catch (ValidationFailed $exception) {
            static::assertCount(6, $exception->result()->errors());
        }
    }

    public function testWarningsDoNotPreventTheCreation(): void
    {
        $line = new ServiceLineDraft('270', null, 'FORFAIT', '2', '5.51', new LineAmountsDraft('11.02', '10.02', '1'), 'SIR12345678901234', null, null);
        $draft = DraftFixtures::draft([$line], null);

        static::assertSame(['warning lines[0].natureCode line.not_eligible'], ViolationAssertions::summary(self::validate($draft, self::context())));
        $request = (new PaymentRequestFactory())->fromDraft(LocalPaymentRequestId::fromString('id'), $draft, self::context());
        static::assertSame('270', $request->lines()->all()[0]->natureCode()->value);
    }

    private static function context(): PaymentRequestContext
    {
        return Fixtures::context(Fixtures::NOW, Fixtures::PAIRING);
    }

    private static function validate(PaymentRequestDraft $draft, PaymentRequestContext $context): ValidationResult
    {
        return (new PaymentRequestFactory())->validate($draft, $context);
    }
}
