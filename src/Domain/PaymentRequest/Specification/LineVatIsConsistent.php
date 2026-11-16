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

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Line total excl. tax + VAT must equal the line total incl. tax within ± 0.05 (ERR_MNT_PREST_HT_TVA).
 * URSSAF only speaks of a "rounding tolerance": the documented ± 0.05 is used to avoid false positives.
 *
 * Violation: "line.vat_mismatch" at "amounts.vatAmount".
 *
 * @implements Validator<ServiceLine>
 */
final readonly class LineVatIsConsistent implements Validator
{
    /**
     * @param ServiceLine $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $amounts = $subject->amounts();
        if ($amounts->totalExclTax()->add($amounts->vatAmount())->isWithinTolerance(
            $amounts->totalInclTax(),
            Money::fromCents(Money::URSSAF_TOLERANCE_CENTS),
        )) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'amounts.vatAmount',
            'line.vat_mismatch',
            'The line total excluding tax plus the VAT must equal the line total including tax (± 0.05).',
        ));
    }
}
