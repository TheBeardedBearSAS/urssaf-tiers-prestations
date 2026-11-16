<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\AtLeastOneLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\DepositWithinLimit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\LinesPeriodsWithinInvoicePeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\TotalsAreConsistent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

final class InvoiceSpecificationsTest extends TestCase
{
    public function testAtLeastOneLine(): void
    {
        static::assertSame([], (new AtLeastOneLine())->validate(Fixtures::validInvoice())->all());

        $empty = Fixtures::invoice(ServiceLines::of(), '0', '0', null);
        static::assertSame(['error lines lines.empty'], ViolationAssertions::summary((new AtLeastOneLine())->validate($empty)));
    }

    /**
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function provideTotals(): iterable
    {
        yield 'exact' => ['11.02', '10.02', []];
        yield 'incl. tax 0.05 above' => ['11.07', '10.02', []];
        yield 'incl. tax 0.06 above' => ['11.08', '10.02', ['error totalInclTax invoice.total_mismatch']];
        yield 'incl. tax 0.05 below' => ['10.97', '10.02', []];
        yield 'incl. tax 0.06 below' => ['10.96', '10.02', ['error totalInclTax invoice.total_mismatch']];
        yield 'excl. tax 0.05 above' => ['11.02', '10.07', []];
        yield 'excl. tax 0.06 above' => ['11.02', '10.08', ['warning totalExclTax invoice.total_excl_tax_mismatch']];
        yield 'excl. tax 0.06 below' => ['11.02', '9.96', ['warning totalExclTax invoice.total_excl_tax_mismatch']];
        yield 'both' => ['12', '12', [
            'error totalInclTax invoice.total_mismatch',
            'warning totalExclTax invoice.total_excl_tax_mismatch',
        ]];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideTotals')]
    public function testTotalsAreConsistent(string $inclTax, string $exclTax, array $expected): void
    {
        $line = Fixtures::line('1', '5.51', '5.51', '5.01', '0.50');
        $invoice = Fixtures::invoice(ServiceLines::of($line, $line), $inclTax, $exclTax, null);

        static::assertSame($expected, ViolationAssertions::summary((new TotalsAreConsistent())->validate($invoice)));
    }

    public function testTotalsAreNotCheckedWithoutLines(): void
    {
        $invoice = Fixtures::invoice(ServiceLines::of(), '10', '10', null);

        static::assertSame([], (new TotalsAreConsistent())->validate($invoice)->all());
    }

    /**
     * @return iterable<string, array{int|null, list<string>}>
     */
    public static function provideDeposits(): iterable
    {
        yield 'no deposit' => [null, []];
        yield 'below half' => [100, []];
        yield 'exactly half' => [551, []];
        yield 'one cent above half' => [552, ['error deposit.amount deposit.exceeds_half']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideDeposits')]
    public function testDepositWithinLimit(?int $cents, array $expected): void
    {
        $deposit = $cents === null ? null : Deposit::of(Money::fromCents($cents), CalendarDate::fromString('2022-02-01'));
        $line = Fixtures::line('1', '5.51', '5.51', '5.01', '0.50');
        $invoice = Fixtures::invoice(ServiceLines::of($line, $line), '11.02', '10.02', $deposit);

        static::assertSame($expected, ViolationAssertions::summary((new DepositWithinLimit())->validate($invoice)));
    }

    public function testLinesPeriodsWithinInvoicePeriod(): void
    {
        $lines = ServiceLines::of(
            Fixtures::typedLine(NatureCode::Housework, null, Fixtures::period(Fixtures::START, Fixtures::END)),
            Fixtures::typedLine(NatureCode::Housework, null, Fixtures::period('2022-02-01T16:39:59Z', Fixtures::END)),
            Fixtures::typedLine(NatureCode::Housework, null, null),
            Fixtures::typedLine(NatureCode::Housework, null, Fixtures::period('2022-02-01T16:41:00Z', '2022-02-01T16:44:00Z')),
            Fixtures::typedLine(NatureCode::Housework, null, Fixtures::period(Fixtures::START, '2022-02-01T16:45:01Z')),
        );
        $invoice = Fixtures::invoice($lines, '27.55', '25.05', null);

        static::assertSame(
            [
                'error lines[1].period line.period_outside_invoice_period',
                'error lines[4].period line.period_outside_invoice_period',
            ],
            ViolationAssertions::summary((new LinesPeriodsWithinInvoicePeriod())->validate($invoice)),
        );
    }
}
