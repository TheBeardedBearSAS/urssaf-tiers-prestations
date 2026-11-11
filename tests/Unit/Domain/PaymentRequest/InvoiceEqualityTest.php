<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\BillingThirdPartyId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\InvoicedCustomer;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineAmounts;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineComplement;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Unit;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\IntervenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Value equality of an invoice and of its parts: equal when every part is equal, different as soon as one differs.
 *
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class InvoiceEqualityTest extends TestCase
{
    private const OTHER_CUSTOMER = 'bf0be430-908a-11eb-a8b3-0242ac130003';

    public function testEqualParts(): void
    {
        static::assertTrue(self::invoice()->equals(self::invoice()));
        static::assertTrue(self::invoice(['deposit' => null])->equals(self::invoice(['deposit' => null])));
        static::assertTrue(self::line()->equals(self::line()));
        static::assertTrue(self::line(['activity' => null, 'period' => null, 'complement' => null])->equals(self::line(['activity' => null, 'period' => null, 'complement' => null])));
        static::assertTrue(ServiceLines::of()->equals(ServiceLines::of()));
        static::assertTrue(self::deposit('1.00', '2022-01-20')->equals(self::deposit('1.00', '2022-01-20')));
        static::assertTrue(self::amounts('5.51', '5.01', '0.50')->equals(self::amounts('5.51', '5.01', '0.50')));
        static::assertTrue(self::customer(Fixtures::PAIRING)->equals(self::customer(Fixtures::PAIRING)));
    }

    /**
     * @return iterable<string, array{string}> keys of {@see self::invoiceDifferences()}
     */
    public static function provideInvoiceDifferences(): iterable
    {
        yield 'customer' => ['customer'];
        yield 'billing third party' => ['billing third party'];
        yield 'number' => ['number'];
        yield 'date' => ['date'];
        yield 'employment period' => ['employment period'];
        yield 'total including tax' => ['total including tax'];
        yield 'total excluding tax' => ['total excluding tax'];
        yield 'lines' => ['lines'];
        yield 'deposit' => ['deposit'];
        yield 'no deposit' => ['no deposit'];
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws InvalidValue
     */
    private static function invoiceDifferences(): array
    {
        return [
            'customer' => ['customer' => InvoicedCustomer::of(UrssafCustomerId::fromString(self::OTHER_CUSTOMER), CalendarDate::fromString('1980-03-29'))],
            'billing third party' => ['billing' => BillingThirdPartyId::fromString('other.fr')],
            'number' => ['number' => InvoiceNumber::fromString('2022-AZ-00002')],
            'date' => ['date' => CalendarDate::fromString('2022-02-02')],
            'employment period' => ['period' => Fixtures::period(Fixtures::START, '2022-02-01T16:50:00Z')],
            'total including tax' => ['inclTax' => Money::fromDecimalString('11.03')],
            'total excluding tax' => ['exclTax' => Money::fromDecimalString('10.03')],
            'lines' => ['lines' => ServiceLines::of(self::line())],
            'deposit' => ['deposit' => self::deposit('2.00', '2022-01-20')],
            'no deposit' => ['deposit' => null],
        ];
    }

    #[DataProvider('provideInvoiceDifferences')]
    public function testInvoicesDifferingByOnePartAreDifferent(string $case): void
    {
        $parts = self::invoiceDifferences()[$case];
        static::assertFalse(self::invoice()->equals(self::invoice($parts)));
        static::assertFalse(self::invoice($parts)->equals(self::invoice()));
    }

    /**
     * @return iterable<string, array{string}> keys of {@see self::lineDifferences()}
     */
    public static function provideLineDifferences(): iterable
    {
        yield 'nature' => ['nature'];
        yield 'activity' => ['activity'];
        yield 'no activity' => ['no activity'];
        yield 'unit' => ['unit'];
        yield 'quantity' => ['quantity'];
        yield 'unit price' => ['unit price'];
        yield 'amounts' => ['amounts'];
        yield 'intervenant' => ['intervenant'];
        yield 'period' => ['period'];
        yield 'no period' => ['no period'];
        yield 'complement' => ['complement'];
        yield 'no complement' => ['no complement'];
    }

    /**
     * @return array<string, array<string, mixed>>
     *
     * @throws InvalidValue
     */
    private static function lineDifferences(): array
    {
        return [
            'nature' => ['nature' => NatureCode::Gardening, 'activity' => null],
            'activity' => ['activity' => ActivityCode::DailyLivingNight],
            'no activity' => ['activity' => null],
            'unit' => ['unit' => Unit::Flat],
            'quantity' => ['quantity' => Quantity::fromDecimalString('2')],
            'unit price' => ['unitPrice' => UnitPrice::fromDecimalString('5.52')],
            'amounts' => ['amounts' => self::amounts('5.52', '5.02', '0.50')],
            'intervenant' => ['intervenant' => IntervenantId::fromString('SAP987654321')],
            'period' => ['period' => Fixtures::period(Fixtures::START, '2022-02-01T16:50:00Z')],
            'no period' => ['period' => null],
            'complement' => ['complement' => LineComplement::fromString('Other')],
            'no complement' => ['complement' => null],
        ];
    }

    #[DataProvider('provideLineDifferences')]
    public function testLinesDifferingByOnePartAreDifferent(string $case): void
    {
        $parts = self::lineDifferences()[$case];
        static::assertFalse(self::line()->equals(self::line($parts)));
        static::assertFalse(self::line($parts)->equals(self::line()));
    }

    public function testLinesAreComparedInOrder(): void
    {
        $first = self::line();
        $second = self::line(['quantity' => Quantity::fromDecimalString('2')]);

        static::assertTrue(ServiceLines::of($first, $second)->equals(ServiceLines::of($first, $second)));
        static::assertFalse(ServiceLines::of($first, $second)->equals(ServiceLines::of($second, $first)));
        static::assertFalse(ServiceLines::of($first, $second)->equals(ServiceLines::of($first, $first)));
        static::assertFalse(ServiceLines::of($first)->equals(ServiceLines::of($first, $second)));
        static::assertFalse(ServiceLines::of($first, $second)->equals(ServiceLines::of($first)));
    }

    public function testDepositsAmountsAndCustomersDifferingByOnePartAreDifferent(): void
    {
        $deposit = self::deposit('1.00', '2022-01-20');
        static::assertFalse($deposit->equals(self::deposit('1.01', '2022-01-20')));
        static::assertFalse($deposit->equals(self::deposit('1.00', '2022-01-21')));

        $amounts = self::amounts('5.51', '5.01', '0.50');
        static::assertFalse($amounts->equals(self::amounts('5.52', '5.01', '0.50')));
        static::assertFalse($amounts->equals(self::amounts('5.51', '5.02', '0.50')));
        static::assertFalse($amounts->equals(self::amounts('5.51', '5.01', '0.51')));

        $customer = self::customer(Fixtures::PAIRING);
        static::assertFalse($customer->equals(InvoicedCustomer::of(UrssafCustomerId::fromString(self::OTHER_CUSTOMER), $customer->birthDate())));
        static::assertFalse($customer->equals(InvoicedCustomer::of($customer->id(), CalendarDate::fromString('1980-03-30'))));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function invoice(array $overrides = []): Invoice
    {
        $base = Fixtures::validInvoice();
        $parts = $overrides + [
            'customer' => $base->customer(),
            'billing' => $base->billingThirdPartyId(),
            'number' => $base->number(),
            'date' => $base->date(),
            'period' => $base->employmentPeriod(),
            'inclTax' => $base->totalInclTax(),
            'exclTax' => $base->totalExclTax(),
            'lines' => $base->lines(),
            'deposit' => self::deposit('1.00', '2022-01-20'),
        ];
        Assert::assertInstanceOf(InvoicedCustomer::class, $parts['customer']);
        Assert::assertInstanceOf(BillingThirdPartyId::class, $parts['billing']);
        Assert::assertInstanceOf(InvoiceNumber::class, $parts['number']);
        Assert::assertInstanceOf(CalendarDate::class, $parts['date']);
        Assert::assertInstanceOf(EmploymentPeriod::class, $parts['period']);
        Assert::assertInstanceOf(Money::class, $parts['inclTax']);
        Assert::assertInstanceOf(Money::class, $parts['exclTax']);
        Assert::assertInstanceOf(ServiceLines::class, $parts['lines']);
        \assert($parts['deposit'] === null || $parts['deposit'] instanceof Deposit, 'Unexpected override type.');

        return new Invoice(
            $parts['customer'],
            $parts['billing'],
            $parts['number'],
            $parts['date'],
            $parts['period'],
            $parts['inclTax'],
            $parts['exclTax'],
            $parts['lines'],
            $parts['deposit'],
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function line(array $overrides = []): ServiceLine
    {
        $parts = $overrides + [
            'nature' => NatureCode::DailyLivingAssistance,
            'activity' => ActivityCode::DailyLivingAdministrativeFees,
            'unit' => Unit::Hour,
            'quantity' => Quantity::fromDecimalString('1'),
            'unitPrice' => UnitPrice::fromDecimalString('5.51'),
            'amounts' => self::amounts('5.51', '5.01', '0.50'),
            'intervenant' => IntervenantId::fromString('SAP123456789'),
            'period' => Fixtures::period(Fixtures::START, Fixtures::END),
            'complement' => LineComplement::fromString('Cleaning'),
        ];
        Assert::assertInstanceOf(NatureCode::class, $parts['nature']);
        \assert($parts['activity'] === null || $parts['activity'] instanceof ActivityCode, 'Unexpected override type.');
        Assert::assertInstanceOf(Unit::class, $parts['unit']);
        Assert::assertInstanceOf(Quantity::class, $parts['quantity']);
        Assert::assertInstanceOf(UnitPrice::class, $parts['unitPrice']);
        Assert::assertInstanceOf(LineAmounts::class, $parts['amounts']);
        Assert::assertInstanceOf(IntervenantId::class, $parts['intervenant']);
        \assert($parts['period'] === null || $parts['period'] instanceof EmploymentPeriod, 'Unexpected override type.');
        \assert($parts['complement'] === null || $parts['complement'] instanceof LineComplement, 'Unexpected override type.');

        return new ServiceLine(
            $parts['nature'],
            $parts['activity'],
            $parts['unit'],
            $parts['quantity'],
            $parts['unitPrice'],
            $parts['amounts'],
            $parts['intervenant'],
            $parts['period'],
            $parts['complement'],
        );
    }

    private static function amounts(string $inclTax, string $exclTax, string $vat): LineAmounts
    {
        return LineAmounts::of(Money::fromDecimalString($inclTax), Money::fromDecimalString($exclTax), Money::fromDecimalString($vat));
    }

    private static function deposit(string $amount, string $date): Deposit
    {
        return Deposit::of(Money::fromDecimalString($amount), CalendarDate::fromString($date));
    }

    private static function customer(string $pairing): InvoicedCustomer
    {
        return Fixtures::context(Fixtures::NOW, $pairing)->customer();
    }
}
