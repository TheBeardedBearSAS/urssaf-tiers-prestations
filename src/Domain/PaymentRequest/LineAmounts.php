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

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Amounts of a service line: total incl. tax (mntPrestationTTC), total excl. tax (mntPrestationHT) and VAT
 * (mntPrestationTVA). Their consistency is checked by {@see Specification\LineVatIsConsistent}.
 */
final readonly class LineAmounts
{
    private function __construct(private Money $totalInclTax, private Money $totalExclTax, private Money $vatAmount) {}

    public static function of(Money $totalInclTax, Money $totalExclTax, Money $vatAmount): self
    {
        return new self($totalInclTax, $totalExclTax, $vatAmount);
    }

    public function totalInclTax(): Money
    {
        return $this->totalInclTax;
    }

    public function totalExclTax(): Money
    {
        return $this->totalExclTax;
    }

    public function vatAmount(): Money
    {
        return $this->vatAmount;
    }

    public function equals(self $other): bool
    {
        return $this->totalInclTax->equals($other->totalInclTax)
            && $this->totalExclTax->equals($other->totalExclTax)
            && $this->vatAmount->equals($other->vatAmount);
    }
}
