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

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\IntervenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;

/**
 * One service of a payment request (InputPrestation).
 *
 * Each component is a self-validating value object; the cross-field rules (VAT, quantity x unit price, activity
 * attached to the nature, eligibility) are NOT enforced here so that every finding can be reported at once: see
 * {@see Specification\InvoiceRules::line()}. {@see PaymentRequest::create()} refuses inconsistent lines.
 */
final readonly class ServiceLine
{
    public function __construct(
        private NatureCode $natureCode,
        private ?ActivityCode $activityCode,
        private Unit $unit,
        private Quantity $quantity,
        private UnitPrice $unitPrice,
        private LineAmounts $amounts,
        private IntervenantId $intervenantId,
        private ?EmploymentPeriod $period,
        private ?LineComplement $complement,
    ) {}

    public function natureCode(): NatureCode
    {
        return $this->natureCode;
    }

    public function activityCode(): ?ActivityCode
    {
        return $this->activityCode;
    }

    public function unit(): Unit
    {
        return $this->unit;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    /**
     * Unit price incl. tax (mntUnitaireTTC, up to 3 decimals).
     */
    public function unitPrice(): UnitPrice
    {
        return $this->unitPrice;
    }

    public function amounts(): LineAmounts
    {
        return $this->amounts;
    }

    /**
     * Worker who performed the service (complement2, required).
     */
    public function intervenantId(): IntervenantId
    {
        return $this->intervenantId;
    }

    public function period(): ?EmploymentPeriod
    {
        return $this->period;
    }

    /**
     * Free text (complement1).
     */
    public function complement(): ?LineComplement
    {
        return $this->complement;
    }

    public function equals(self $other): bool
    {
        return $this->natureCode === $other->natureCode
            && $this->activityCode === $other->activityCode
            && $this->unit === $other->unit
            && $this->quantity->equals($other->quantity)
            && $this->unitPrice->equals($other->unitPrice)
            && $this->amounts->equals($other->amounts)
            && $this->intervenantId->equals($other->intervenantId)
            && ($this->period instanceof EmploymentPeriod
                ? $other->period instanceof EmploymentPeriod && $this->period->equals($other->period)
                : !$other->period instanceof EmploymentPeriod)
            && $this->complement?->value() === $other->complement?->value();
    }
}
