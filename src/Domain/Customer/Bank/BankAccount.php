<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank;

/**
 * Bank details of the customer (coordonneeBancaire).
 */
final readonly class BankAccount
{
    private function __construct(private Iban $iban, private Bic $bic, private AccountHolder $holder) {}

    public static function of(Iban $iban, Bic $bic, AccountHolder $holder): self
    {
        return new self($iban, $bic, $holder);
    }

    public function iban(): Iban
    {
        return $this->iban;
    }

    public function bic(): Bic
    {
        return $this->bic;
    }

    public function holder(): AccountHolder
    {
        return $this->holder;
    }

    public function equals(self $other): bool
    {
        return $this->iban->equals($other->iban) && $this->bic->equals($other->bic) && $this->holder->equals($other->holder);
    }
}
