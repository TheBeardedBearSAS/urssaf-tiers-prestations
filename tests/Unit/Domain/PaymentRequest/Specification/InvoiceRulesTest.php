<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\AllOf;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\EveryLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\InvoiceRules;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\LineVatIsConsistent;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\NatureIsEligibleForTaxCredit;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

final class InvoiceRulesTest extends TestCase
{
    public function testAllOfMergesInOrder(): void
    {
        $line = Fixtures::typedLine(NatureCode::Miscellaneous, null, null);
        $inconsistent = Fixtures::line('1', '5.51', '5.51', '1', '0');

        static::assertSame(
            ['warning natureCode line.not_eligible'],
            ViolationAssertions::summary(AllOf::of(new LineVatIsConsistent(), new NatureIsEligibleForTaxCredit())->validate($line)),
        );
        static::assertSame(
            ['error amounts.vatAmount line.vat_mismatch', 'error amounts.vatAmount line.vat_mismatch'],
            ViolationAssertions::summary(AllOf::of(new LineVatIsConsistent(), new LineVatIsConsistent())->validate($inconsistent)),
        );
    }

    public function testEveryLineNestsViolations(): void
    {
        $lines = ServiceLines::of(
            Fixtures::typedLine(NatureCode::Housework, null, null),
            Fixtures::typedLine(NatureCode::Miscellaneous, null, null),
            Fixtures::typedLine(NatureCode::Miscellaneous, null, null),
        );
        $result = (new EveryLine(new NatureIsEligibleForTaxCredit()))->validate(Fixtures::invoice($lines, '16.53', '15.03', null));

        static::assertSame(
            ['warning lines[1].natureCode line.not_eligible', 'warning lines[2].natureCode line.not_eligible'],
            ViolationAssertions::summary($result),
        );
    }

    public function testLineRules(): void
    {
        $line = Fixtures::typedLine(NatureCode::Miscellaneous, ActivityCode::HouseworkNight, null);
        $inconsistent = Fixtures::line('2', '5.51', '5.51', '1', '0');

        static::assertSame(
            ['error activityCode line.activity_not_in_nature', 'warning natureCode line.not_eligible'],
            ViolationAssertions::summary(InvoiceRules::line()->validate($line)),
        );
        static::assertSame(
            ['error amounts.vatAmount line.vat_mismatch', 'error amounts.totalInclTax line.total_mismatch'],
            ViolationAssertions::summary(InvoiceRules::line()->validate($inconsistent)),
        );
    }

    public function testInvoiceRules(): void
    {
        $line = Fixtures::typedLine(NatureCode::Housework, null, Fixtures::period('2022-02-01T16:00:00Z', Fixtures::END));
        $deposit = Deposit::of(Money::fromCents(500), CalendarDate::fromString('2022-02-01'));
        $invoice = Fixtures::invoice(ServiceLines::of($line), '9', '9', $deposit);

        static::assertSame(
            [
                'error totalInclTax invoice.total_mismatch',
                'warning totalExclTax invoice.total_excl_tax_mismatch',
                'error deposit.amount deposit.exceeds_half',
                'error lines[0].period line.period_outside_invoice_period',
            ],
            ViolationAssertions::summary(InvoiceRules::invoice()->validate($invoice)),
        );
        static::assertSame(
            ['error lines lines.empty'],
            ViolationAssertions::summary(InvoiceRules::invoice()->validate(Fixtures::invoice(ServiceLines::of(), '0', '0', null))),
        );
        static::assertSame(
            ['error deposit.amount deposit.exceeds_half'],
            ViolationAssertions::summary(InvoiceRules::invoice()->validate(Fixtures::invoice(
                ServiceLines::of(Fixtures::typedLine(NatureCode::Housework, null, null)),
                '5.51',
                '5.01',
                $deposit,
            ))),
        );
    }

    public function testIntrinsicRules(): void
    {
        $lines = ServiceLines::of(Fixtures::typedLine(NatureCode::Miscellaneous, null, null));

        static::assertSame([], InvoiceRules::intrinsic()->validate(Fixtures::validInvoice())->all());
        static::assertSame(
            ['warning lines[0].natureCode line.not_eligible', 'error totalInclTax invoice.total_mismatch'],
            ViolationAssertions::summary(InvoiceRules::intrinsic()->validate(Fixtures::invoice($lines, '9', '5.01', null))),
        );
    }

    public function testContextualRules(): void
    {
        $invoice = Fixtures::validInvoice();

        static::assertSame([], InvoiceRules::contextual(Fixtures::at(Fixtures::NOW), Fixtures::at(Fixtures::PAIRING))->validate($invoice)->all());
        static::assertSame(
            ['error employmentPeriod employment_period.in_future', 'error employmentPeriod employment_period.before_pairing'],
            ViolationAssertions::summary(InvoiceRules::contextual(Fixtures::at(Fixtures::PAIRING), Fixtures::at(Fixtures::NOW))->validate($invoice)),
        );
    }
}
