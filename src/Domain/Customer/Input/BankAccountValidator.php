<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\AccountHolder;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\BankAccount;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Bic;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Iban;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;

/**
 * Validates raw bank details. Paths: iban, bic, holder.
 *
 * @implements Validator<BankAccountDraft>
 */
final readonly class BankAccountValidator implements Validator
{
    /**
     * @param BankAccountDraft $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($subject, $collector);

        return $collector->result();
    }

    /**
     * Returns the bank account, or null after reporting the violations to the collector.
     */
    public function parse(BankAccountDraft $draft, ViolationCollector $collector): ?BankAccount
    {
        $iban = $collector->required('iban', 'iban', $draft->iban, Iban::fromString(...));
        $bic = $collector->required('bic', 'bic', $draft->bic, Bic::fromString(...));
        $holder = $collector->required('holder', 'account_holder', $draft->holder, AccountHolder::fromString(...));
        if ($iban === null || $bic === null || $holder === null) {
            return null;
        }

        return BankAccount::of($iban, $bic, $holder);
    }
}
