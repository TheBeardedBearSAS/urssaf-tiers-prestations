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
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Customer a payment request is addressed to (idClient + dateNaissanceClient). URSSAF answers ERR_PARTICULIER_INCONNU
 * when the birth date does not match the registered one.
 */
final readonly class InvoicedCustomer
{
    private function __construct(private UrssafCustomerId $id, private CalendarDate $birthDate) {}

    public static function of(UrssafCustomerId $id, CalendarDate $birthDate): self
    {
        return new self($id, $birthDate);
    }

    public function id(): UrssafCustomerId
    {
        return $this->id;
    }

    public function birthDate(): CalendarDate
    {
        return $this->birthDate;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id) && $this->birthDate->equals($other->birthDate);
    }
}
