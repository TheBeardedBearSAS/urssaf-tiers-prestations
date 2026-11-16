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
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Transfer to the provider (infoVirement of method 070): amount and forecast or actual value date.
 */
final readonly class TransferInfo
{
    private function __construct(private Money $amount, private CalendarDate $valueDate) {}

    public static function of(Money $amount, CalendarDate $valueDate): self
    {
        return new self($amount, $valueDate);
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function valueDate(): CalendarDate
    {
        return $this->valueDate;
    }
}
