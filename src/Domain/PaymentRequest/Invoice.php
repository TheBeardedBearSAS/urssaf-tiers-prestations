<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Business content of a payment request, i.e. everything sent to URSSAF by method 050 (InputDemandePaiement).
 *
 * Components are self-validating value objects; the cross-field rules are reported by
 * {@see Specification\InvoiceRules} instead of being thrown, and enforced by {@see PaymentRequest::create()}.
 */
final readonly class Invoice
{
    public function __construct(
        private InvoicedCustomer $customer,
        private BillingThirdPartyId $billingThirdPartyId,
        private InvoiceNumber $number,
        private CalendarDate $date,
        private EmploymentPeriod $employmentPeriod,
        private Money $totalInclTax,
        private Money $totalExclTax,
        private ServiceLines $lines,
        private ?Deposit $deposit,
    ) {}

    public function customer(): InvoicedCustomer
    {
        return $this->customer;
    }

    public function billingThirdPartyId(): BillingThirdPartyId
    {
        return $this->billingThirdPartyId;
    }

    public function number(): InvoiceNumber
    {
        return $this->number;
    }

    /**
     * Invoice issue date (dateFacture).
     */
    public function date(): CalendarDate
    {
        return $this->date;
    }

    /**
     * From the start of the first service to the end of the last one (dateDebutEmploi / dateFinEmploi).
     */
    public function employmentPeriod(): EmploymentPeriod
    {
        return $this->employmentPeriod;
    }

    public function totalInclTax(): Money
    {
        return $this->totalInclTax;
    }

    public function totalExclTax(): Money
    {
        return $this->totalExclTax;
    }

    public function lines(): ServiceLines
    {
        return $this->lines;
    }

    public function deposit(): ?Deposit
    {
        return $this->deposit;
    }

    public function equals(self $other): bool
    {
        return $this->customer->equals($other->customer)
            && $this->billingThirdPartyId->equals($other->billingThirdPartyId)
            && $this->number->equals($other->number)
            && $this->date->equals($other->date)
            && $this->employmentPeriod->equals($other->employmentPeriod)
            && $this->totalInclTax->equals($other->totalInclTax)
            && $this->totalExclTax->equals($other->totalExclTax)
            && $this->lines->equals($other->lines)
            && ($this->deposit instanceof Deposit
                ? $other->deposit instanceof Deposit && $this->deposit->equals($other->deposit)
                : !$other->deposit instanceof Deposit);
    }
}
