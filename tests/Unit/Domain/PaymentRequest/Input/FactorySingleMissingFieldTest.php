<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\LineAmountsDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\ServiceLineDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification\ViolationAssertions;

/**
 * Each required value missing alone is reported alone (and never breaks the reading of the other values).
 */
final class FactorySingleMissingFieldTest extends TestCase
{
    /**
     * @return iterable<string, array{PaymentRequestDraft, string}>
     */
    public static function provideInvoiceDrafts(): iterable
    {
        $o = DraftFixtures::official();
        $lines = $o->lines;
        $period = $o->employmentPeriod;
        $deposit = $o->deposit;

        yield 'invoice number' => [new PaymentRequestDraft('r', null, '2022-02-01', $period, '11.02', '10.02', $lines, $deposit), 'invoiceNumber'];
        yield 'invoice date' => [new PaymentRequestDraft('r', 'n', null, $period, '11.02', '10.02', $lines, $deposit), 'invoiceDate'];
        yield 'employment period' => [new PaymentRequestDraft('r', 'n', '2022-02-01', null, '11.02', '10.02', $lines, $deposit), 'employmentPeriod'];
        yield 'total incl. tax' => [new PaymentRequestDraft('r', 'n', '2022-02-01', $period, null, '10.02', $lines, $deposit), 'totalInclTax'];
        yield 'total excl. tax' => [new PaymentRequestDraft('r', 'n', '2022-02-01', $period, '11.02', null, $lines, $deposit), 'totalExclTax'];
        yield 'reference' => [new PaymentRequestDraft(null, 'n', '2022-02-01', $period, '11.02', '10.02', $lines, $deposit), 'externalReference'];
    }

    #[DataProvider('provideInvoiceDrafts')]
    public function testInvoiceValue(PaymentRequestDraft $draft, string $path): void
    {
        static::assertSame(['error ' . $path . ' field.required'], self::summary($draft));
    }

    /**
     * @return iterable<string, array{ServiceLineDraft, string}>
     */
    public static function provideLineDrafts(): iterable
    {
        $a = DraftFixtures::firstLine()->amounts;
        $id = 'SAP123456789';

        yield 'nature' => [new ServiceLineDraft(null, null, 'HEURE', '1', '5.51', $a, $id, null, null), 'natureCode'];
        yield 'unit' => [new ServiceLineDraft('60', null, null, '1', '5.51', $a, $id, null, null), 'unit'];
        yield 'quantity' => [new ServiceLineDraft('60', null, 'HEURE', null, '5.51', $a, $id, null, null), 'quantity'];
        yield 'unit price' => [new ServiceLineDraft('60', null, 'HEURE', '1', null, $a, $id, null, null), 'unitPrice'];
        yield 'amounts' => [new ServiceLineDraft('60', null, 'HEURE', '1', '5.51', null, $id, null, null), 'amounts'];
        yield 'intervenant' => [new ServiceLineDraft('60', null, 'HEURE', '1', '5.51', $a, null, null, null), 'intervenantId'];
        yield 'total incl. tax' => [self::lineWith(new LineAmountsDraft(null, '5.01', '0.50')), 'amounts.totalInclTax'];
        yield 'total excl. tax' => [self::lineWith(new LineAmountsDraft('5.51', null, '0.50')), 'amounts.totalExclTax'];
        yield 'vat' => [self::lineWith(new LineAmountsDraft('5.51', '5.01', null)), 'amounts.vatAmount'];
    }

    #[DataProvider('provideLineDrafts')]
    public function testLineValue(ServiceLineDraft $line, string $path): void
    {
        static::assertSame(['error lines[0].' . $path . ' field.required'], self::summary(DraftFixtures::draft([$line], null)));
    }

    private static function lineWith(LineAmountsDraft $amounts): ServiceLineDraft
    {
        return new ServiceLineDraft('60', null, 'HEURE', '1', '5.51', $amounts, 'SAP123456789', null, null);
    }

    /**
     * @return list<string>
     */
    private static function summary(PaymentRequestDraft $draft): array
    {
        return ViolationAssertions::summary(
            (new PaymentRequestFactory())->validate($draft, Fixtures::context(Fixtures::NOW, Fixtures::PAIRING)),
        );
    }
}
