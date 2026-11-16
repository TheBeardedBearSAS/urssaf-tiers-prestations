<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * The employment period must not start before the pairing acceptance instant; starting exactly at that instant is
 * accepted (URSSAF answers ERR_LIEN_PARTICULIER_PRESTATAIRE otherwise).
 *
 * Violation: "employment_period.before_pairing" at "employmentPeriod".
 *
 * @implements Validator<Invoice>
 */
final readonly class EmploymentPeriodNotBeforePairing implements Validator
{
    public function __construct(private Instant $pairingAcceptedAt) {}

    /**
     * @param Invoice $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if (!$subject->employmentPeriod()->startsBefore($this->pairingAcceptedAt)) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'employmentPeriod',
            'employment_period.before_pairing',
            'The employment period must not start before the pairing acceptance of the customer.',
        ));
    }
}
