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
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Unit;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

final class OfficialExampleTest extends TestCase
{
    public function testTheOfficialExampleIsValid(): void
    {
        $result = (new PaymentRequestFactory())->validate(DraftFixtures::official(), Fixtures::context(Fixtures::NOW, Fixtures::PAIRING));

        static::assertSame([], $result->all());
    }

    public function testTheOfficialExampleBuildsAPendingRequest(): void
    {
        $request = (new PaymentRequestFactory())->fromDraft(
            LocalPaymentRequestId::fromString('local-1'),
            DraftFixtures::official(),
            Fixtures::context(Fixtures::NOW, Fixtures::PAIRING),
        );

        static::assertSame('local-1', $request->id()->value());
        static::assertSame('tenant-1', $request->tenantId()->value());
        static::assertSame('invoice-42', $request->externalReference()->value());
        static::assertSame(SubmissionState::Pending, $request->state());
        static::assertSame('6a56a628-b09e-8707-787e-10f218a2d550', $request->urssafCustomerId()->value());
        static::assertSame('1980-03-01', $request->customerBirthDate()->toString());
        static::assertSame('id-facturation', $request->billingThirdPartyId()->value());
        static::assertSame('2022-AZ-00001', $request->invoiceNumber()->value());
        static::assertSame('2022-02-01', $request->invoiceDate()->toString());
        static::assertSame(Fixtures::START, $request->employmentPeriod()->start()->toIso8601());
        static::assertSame(Fixtures::END, $request->employmentPeriod()->end()->toIso8601());
        static::assertSame('Europe/Paris', $request->employmentPeriod()->businessTimeZone()->getName());
        static::assertSame('11.02', $request->totalInclTax()->toDecimalString());
        static::assertSame('10.02', $request->totalExclTax()->toDecimalString());
        $deposit = $request->deposit();
        static::assertNotNull($deposit);
        static::assertSame('2.75', $deposit->amount()->toDecimalString());
        static::assertSame('2022-02-01', $deposit->paymentDate()->toString());
        static::assertSame(Fixtures::NOW, $request->timeline()->createdAt()->toIso8601());
        static::assertCount(1, $request->releaseEvents());
    }

    public function testTheOfficialLines(): void
    {
        $request = (new PaymentRequestFactory())->fromDraft(
            LocalPaymentRequestId::fromString('local-1'),
            DraftFixtures::official(),
            Fixtures::context(Fixtures::NOW, Fixtures::PAIRING),
        );
        [$first, $second] = $request->lines()->all();

        static::assertCount(2, $request->lines()->all());
        static::assertSame(NatureCode::Housework, $first->natureCode());
        static::assertNull($first->activityCode());
        static::assertSame(Unit::Hour, $first->unit());
        static::assertSame('1', $first->quantity()->toDecimalString());
        static::assertSame('5.51', $first->unitPrice()->toDecimalString());
        static::assertSame('5.51', $first->amounts()->totalInclTax()->toDecimalString());
        static::assertSame('5.01', $first->amounts()->totalExclTax()->toDecimalString());
        static::assertSame('0.50', $first->amounts()->vatAmount()->toDecimalString());
        static::assertSame('SAP123456789', $first->intervenantId()->value());
        static::assertSame(Fixtures::START, $first->period()?->start()->toIso8601());
        static::assertSame('Complément 1', $first->complement()?->value());
        static::assertSame(NatureCode::DailyLivingAssistance, $second->natureCode());
        static::assertSame(ActivityCode::DailyLivingAdministrativeFees, $second->activityCode());
        static::assertSame(Unit::Flat, $second->unit());
        static::assertSame('SAP123456780', $second->intervenantId()->value());
        static::assertNull($second->complement());
    }
}
