<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SearchCriterion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;

final class PaymentRequestSearchByInvoiceTest extends TestCase
{
    /**
     * @return list<InvoiceNumber>
     */
    private static function invoiceNumbers(int $count): array
    {
        return array_map(static fn(int $i): InvoiceNumber => InvoiceNumber::fromString('F-' . $i), $count > 0 ? range(1, $count) : []);
    }

    public function testByInvoiceNumbers(): void
    {
        $numbers = self::invoiceNumbers(1);

        $search = PaymentRequestSearch::byInvoiceNumbers(...['named' => $numbers[0]]);

        static::assertSame(SearchCriterion::InvoiceNumbers, $search->criterion());
        static::assertSame($numbers, $search->invoiceNumbers());
        static::assertSame([], $search->paymentRequestIds());
        static::assertNull($search->from());
        static::assertNull($search->to());
    }

    public function testEmptyInvoiceNumbersAreRejected(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('payment_request_search'));

        PaymentRequestSearch::byInvoiceNumbers();
    }

    public function testTooManyInvoiceNumbersAreRejected(): void
    {
        try {
            PaymentRequestSearch::byInvoiceNumbers(...self::invoiceNumbers(11));
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('payment_request_search.too_many_items', $exception->violationCode());
            static::assertSame(['max_items' => 10], $exception->params());
        }
    }

    public function testByInvoicePeriod(): void
    {
        $from = CalendarDate::fromString('2022-01-01');
        $to = CalendarDate::fromString('2022-01-31');

        $search = PaymentRequestSearch::byInvoicePeriod($from, $to);

        static::assertSame(SearchCriterion::InvoicePeriod, $search->criterion());
        static::assertSame($from, $search->from());
        static::assertSame($to, $search->to());
        static::assertSame([], $search->paymentRequestIds());
        static::assertSame([], $search->invoiceNumbers());
        static::assertSame($from, PaymentRequestSearch::byInvoicePeriod($from, $from)->to());
    }

    public function testInvertedPeriodIsRejected(): void
    {
        try {
            PaymentRequestSearch::byInvoicePeriod(CalendarDate::fromString('2022-01-02'), CalendarDate::fromString('2022-01-01'));
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('payment_request_search.invalid_period', $exception->violationCode());
            static::assertSame('Invalid payment_request_search: the period end must not be before its start.', $exception->getMessage());
        }
    }
}
