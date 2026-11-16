<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestContext;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Parses the invoice part of a draft. Returns null when a required part could not be built.
 *
 * @internal
 */
final readonly class InvoiceReader
{
    private PeriodReader $periods;

    private ServiceLineReader $lines;

    public function __construct(private DraftReader $reader, private PaymentRequestContext $context)
    {
        $this->periods = new PeriodReader($reader, $context->businessTimeZone());
        $this->lines = new ServiceLineReader($reader, $this->periods);
    }

    public function read(PaymentRequestDraft $draft): ?Invoice
    {
        $number = $this->reader->required('invoiceNumber', $draft->invoiceNumber, InvoiceNumber::fromString(...));
        $date = $this->reader->required('invoiceDate', $draft->invoiceDate, CalendarDate::fromString(...));
        $period = $this->periods->required('employmentPeriod', $draft->employmentPeriod);
        $totalInclTax = $this->reader->required('totalInclTax', $draft->totalInclTax, Money::fromDecimalString(...));
        $totalExclTax = $this->reader->required('totalExclTax', $draft->totalExclTax, Money::fromDecimalString(...));
        $lines = $this->lines($draft->lines);
        $deposit = $this->deposit($draft->deposit);
        if ($number === null || $date === null || !$period instanceof EmploymentPeriod || $totalInclTax === null || $totalExclTax === null) {
            return null;
        }

        return $lines instanceof ServiceLines ? new Invoice(
            $this->context->customer(),
            $this->context->billingThirdPartyId(),
            $number,
            $date,
            $period,
            $totalInclTax,
            $totalExclTax,
            $lines,
            $deposit,
        ) : null;
    }

    /**
     * @param list<ServiceLineDraft> $drafts
     */
    private function lines(array $drafts): ?ServiceLines
    {
        $lines = [];
        foreach ($drafts as $index => $draft) {
            $lines[] = $this->lines->read(\sprintf('lines[%d]', $index), $draft);
        }
        $parsed = array_filter($lines, static fn(?ServiceLine $line): bool => $line instanceof ServiceLine);

        return \count($parsed) === \count($lines) ? ServiceLines::of(...$parsed) : null;
    }

    private function deposit(?DepositDraft $draft): ?Deposit
    {
        if (!$draft instanceof DepositDraft) {
            return null;
        }
        $amount = $this->reader->required('deposit.amount', $draft->amount, Money::fromDecimalString(...));
        $paymentDate = $this->reader->required('deposit.paymentDate', $draft->paymentDate, CalendarDate::fromString(...));
        if ($amount === null || $paymentDate === null) {
            return null;
        }

        return $this->reader->attempt('deposit.amount', static fn(): Deposit => Deposit::of($amount, $paymentDate));
    }
}
