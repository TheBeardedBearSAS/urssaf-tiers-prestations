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

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\BankAccount;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\ContactDetails;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Customer;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\CivilStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;

/**
 * Builds a {@see Customer} from raw input, reporting every violation at once.
 *
 * Paths: civility, birthName, usageName, firstNames, birthDate, birthPlace.*, mobilePhone, email, address.*,
 * bankAccount.* (see {@see BirthPlaceValidator}, {@see PostalAddressValidator}, {@see BankAccountValidator}).
 * The birth date is only checked for format here: "not in the future" is checked when the registration is requested.
 *
 * @implements Validator<CustomerDraft>
 */
final readonly class CustomerFactory implements Validator
{
    /**
     * @param CustomerDraft $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($subject, $collector);

        return $collector->result();
    }

    /**
     * @throws ValidationFailed with every violation
     */
    public function fromDraft(CustomerDraft $draft): Customer
    {
        $collector = new ViolationCollector();

        return $this->parse($draft, $collector) ?? throw ValidationFailed::withResult($collector->result());
    }

    private function parse(CustomerDraft $draft, ViolationCollector $collector): ?Customer
    {
        $civilStatus = (new CivilStatusParser())->parse($draft, $collector);
        $contactDetails = (new ContactDetailsParser())->parse($draft, $collector);
        $bankCollector = new ViolationCollector();
        $bankAccount = (new BankAccountValidator())->parse($draft->bankAccount ?? new BankAccountDraft(null, null, null), $bankCollector);
        $collector->merge($bankCollector->result(), 'bankAccount');
        if (!$civilStatus instanceof CivilStatus || !$contactDetails instanceof ContactDetails || !$bankAccount instanceof BankAccount) {
            return null;
        }

        return Customer::of($civilStatus, $contactDetails, $bankAccount);
    }
}
