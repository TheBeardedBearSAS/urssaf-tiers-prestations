<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\BillingThirdPartyId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\InvoicedCustomer;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineAmounts;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestContext;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Unit;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\IntervenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Domain objects shared by the payment request tests, based on the official example of api-reference §2.3.
 */
final class Fixtures
{
    public const NOW = '2022-03-01T00:00:00Z';
    public const PAIRING = '2022-01-15T10:00:00Z';
    public const START = '2022-02-01T16:40:00Z';
    public const END = '2022-02-01T16:45:00Z';

    public static function at(string $iso8601): Instant
    {
        return Instant::fromIso8601($iso8601);
    }

    public static function period(string $start, string $end): EmploymentPeriod
    {
        return EmploymentPeriod::between(self::at($start), self::at($end), new DateTimeZone('Europe/Paris'));
    }

    public static function context(string $now, string $pairingAcceptedAt): PaymentRequestContext
    {
        return new PaymentRequestContext(
            TenantId::fromString('tenant-1'),
            InvoicedCustomer::of(
                UrssafCustomerId::fromString('6a56a628-b09e-8707-787e-10f218a2d550'),
                CalendarDate::fromString('1980-03-01'),
            ),
            BillingThirdPartyId::fromString('id-facturation'),
            self::at($pairingAcceptedAt),
            self::at($now),
            new DateTimeZone('Europe/Paris'),
        );
    }

    /**
     * Housework line without activity nor period.
     */
    public static function line(string $quantity, string $unitPrice, string $inclTax, string $exclTax, string $vat): ServiceLine
    {
        return new ServiceLine(
            NatureCode::Housework,
            null,
            Unit::Hour,
            Quantity::fromDecimalString($quantity),
            UnitPrice::fromDecimalString($unitPrice),
            LineAmounts::of(
                Money::fromDecimalString($inclTax),
                Money::fromDecimalString($exclTax),
                Money::fromDecimalString($vat),
            ),
            IntervenantId::fromString('SAP123456789'),
            null,
            null,
        );
    }

    /**
     * Consistent 5.51 line of the given nature, activity and period.
     */
    public static function typedLine(NatureCode $nature, ?ActivityCode $activity, ?EmploymentPeriod $period): ServiceLine
    {
        $line = self::line('1', '5.51', '5.51', '5.01', '0.50');

        return new ServiceLine(
            $nature,
            $activity,
            $line->unit(),
            $line->quantity(),
            $line->unitPrice(),
            $line->amounts(),
            $line->intervenantId(),
            $period,
            null,
        );
    }

    public static function invoice(ServiceLines $lines, string $inclTax, string $exclTax, ?Deposit $deposit): Invoice
    {
        $context = self::context(self::NOW, self::PAIRING);

        return new Invoice(
            $context->customer(),
            $context->billingThirdPartyId(),
            InvoiceNumber::fromString('2022-AZ-00001'),
            CalendarDate::fromString('2022-02-01'),
            self::period(self::START, self::END),
            Money::fromDecimalString($inclTax),
            Money::fromDecimalString($exclTax),
            $lines,
            $deposit,
        );
    }

    /**
     * Two consistent 5.51 lines, totals 11.02 / 10.02, no deposit.
     */
    public static function validInvoice(): Invoice
    {
        $line = self::line('1', '5.51', '5.51', '5.01', '0.50');

        return self::invoice(ServiceLines::of($line, $line), '11.02', '10.02', null);
    }

    public static function pendingRequest(): PaymentRequest
    {
        return PaymentRequest::create(
            LocalPaymentRequestId::fromString('local-1'),
            TenantId::fromString('tenant-1'),
            ExternalReference::fromString('invoice-42'),
            self::validInvoice(),
            self::at(self::NOW),
        );
    }

    /**
     * Request driven to the given state at NOW (submitted with URSSAF id "urssaf-1" and status 10), events released.
     */
    public static function requestIn(SubmissionState $state): PaymentRequest
    {
        $request = self::pendingRequest();
        $now = self::at(self::NOW);
        if ($state !== SubmissionState::Pending) {
            $request->startSubmission($now);
        }
        if ($state === SubmissionState::Submitted) {
            $request->markAccepted(PaymentRequestId::fromString('urssaf-1'), '10', $now);
        }
        if ($state === SubmissionState::Rejected) {
            $request->markRejected([SubmissionError::of('ERR_FACTURE_DOUBLON', 'm', 'd')], 'ERR_FACTURE_DOUBLON', $now);
        }
        if ($state === SubmissionState::Error) {
            $request->markFailed([], $now);
        }
        if ($state === SubmissionState::InvalidCredentials) {
            $request->markInvalidCredentials($now);
        }
        $request->releaseEvents();

        return $request;
    }
}
