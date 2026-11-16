<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineAmounts;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineComplement;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Unit;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\IntervenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;

final class InvoiceValueObjectsTest extends TestCase
{
    public function testDeposit(): void
    {
        $deposit = Deposit::of(Money::fromCents(275), CalendarDate::fromString('2022-02-01'));

        static::assertSame(275, $deposit->amount()->cents());
        static::assertSame('2022-02-01', $deposit->paymentDate()->toString());
    }

    public function testZeroDepositIsRejected(): void
    {
        $this->expectExceptionObject(InvalidValue::notPositive('deposit'));

        Deposit::of(Money::fromCents(0), CalendarDate::fromString('2022-02-01'));
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function provideDepositLimits(): iterable
    {
        yield 'exactly half of an even total' => [551, 1102, false];
        yield 'one cent above half of an even total' => [552, 1102, true];
        yield 'below half of an odd total' => [550, 1101, false];
        yield 'above half of an odd total' => [551, 1101, true];
        yield 'one cent below half' => [550, 1102, false];
        yield 'above the total' => [2000, 1000, true];
    }

    #[DataProvider('provideDepositLimits')]
    public function testExceedsHalfOf(int $deposit, int $total, bool $expected): void
    {
        static::assertSame(
            $expected,
            Deposit::of(Money::fromCents($deposit), CalendarDate::fromString('2022-02-01'))->exceedsHalfOf(Money::fromCents($total)),
        );
    }

    public function testServiceLine(): void
    {
        $amounts = LineAmounts::of(Money::fromCents(551), Money::fromCents(501), Money::fromCents(50));
        $period = Fixtures::period(Fixtures::START, Fixtures::END);
        $line = new ServiceLine(
            NatureCode::DailyLivingAssistance,
            ActivityCode::DailyLivingNight,
            Unit::Flat,
            Quantity::fromDecimalString('1.5'),
            UnitPrice::fromDecimalString('3.674'),
            $amounts,
            IntervenantId::fromString('SIR12345678901234'),
            $period,
            LineComplement::fromString('note'),
        );

        static::assertSame(NatureCode::DailyLivingAssistance, $line->natureCode());
        static::assertSame(ActivityCode::DailyLivingNight, $line->activityCode());
        static::assertSame(Unit::Flat, $line->unit());
        static::assertSame(1500, $line->quantity()->thousandths());
        static::assertSame(3674, $line->unitPrice()->thousandths());
        static::assertSame($amounts, $line->amounts());
        static::assertSame('SIR12345678901234', $line->intervenantId()->value());
        static::assertSame($period, $line->period());
        static::assertSame('note', $line->complement()?->value());
    }

    public function testLineAmounts(): void
    {
        $amounts = LineAmounts::of(Money::fromCents(551), Money::fromCents(501), Money::fromCents(50));

        static::assertSame(551, $amounts->totalInclTax()->cents());
        static::assertSame(501, $amounts->totalExclTax()->cents());
        static::assertSame(50, $amounts->vatAmount()->cents());
    }

    public function testServiceLines(): void
    {
        $first = Fixtures::line('1', '5.51', '5.51', '5.01', '0.50');
        $second = Fixtures::line('2', '10', '20.00', '18.18', '1.82');
        $lines = ServiceLines::of($first, $second);

        static::assertSame([$first, $second], $lines->all());
        static::assertSame([$first], ServiceLines::of(...['named' => $first])->all());
        static::assertFalse($lines->isEmpty());
        static::assertSame(2551, $lines->totalInclTax()->cents());
        static::assertSame(2319, $lines->totalExclTax()->cents());
    }

    public function testEmptyServiceLines(): void
    {
        $lines = ServiceLines::of();

        static::assertSame([], $lines->all());
        static::assertTrue($lines->isEmpty());
        static::assertSame(0, $lines->totalInclTax()->cents());
        static::assertSame(0, $lines->totalExclTax()->cents());
    }

    public function testInvoice(): void
    {
        $lines = ServiceLines::of(Fixtures::line('1', '5.51', '5.51', '5.01', '0.50'));
        $deposit = Deposit::of(Money::fromCents(100), CalendarDate::fromString('2022-01-31'));
        $invoice = Fixtures::invoice($lines, '5.51', '5.01', $deposit);

        static::assertSame('6a56a628-b09e-8707-787e-10f218a2d550', $invoice->customer()->id()->value());
        static::assertSame('1980-03-01', $invoice->customer()->birthDate()->toString());
        static::assertSame('id-facturation', $invoice->billingThirdPartyId()->value());
        static::assertSame('2022-AZ-00001', $invoice->number()->value());
        static::assertSame('2022-02-01', $invoice->date()->toString());
        static::assertSame(Fixtures::START, $invoice->employmentPeriod()->start()->toIso8601());
        static::assertSame(551, $invoice->totalInclTax()->cents());
        static::assertSame(501, $invoice->totalExclTax()->cents());
        static::assertSame($lines, $invoice->lines());
        static::assertSame($deposit, $invoice->deposit());
    }
}
