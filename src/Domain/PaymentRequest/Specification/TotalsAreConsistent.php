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
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Invoice totals versus the sum of the lines, ± 0.05 bounds included:
 * - incl. tax: error "invoice.total_mismatch" at "totalInclTax" (ERR_TOTAL_PRESTATIONS);
 * - excl. tax: warning "invoice.total_excl_tax_mismatch" at "totalExclTax". URSSAF documents no such control, so the
 *   request is not blocked, but the host is told that its invoice is probably inconsistent.
 *
 * Nothing is reported for an invoice without lines (see {@see AtLeastOneLine}).
 *
 * @implements Validator<Invoice>
 */
final readonly class TotalsAreConsistent implements Validator
{
    /**
     * @param Invoice $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $lines = $subject->lines();
        $tolerance = Money::fromCents(Money::URSSAF_TOLERANCE_CENTS);
        $result = ValidationResult::valid();
        if ($lines->isEmpty()) {
            return $result;
        }
        if (!$lines->totalInclTax()->isWithinTolerance($subject->totalInclTax(), $tolerance)) {
            $result = $result->with(Violation::error(
                'totalInclTax',
                'invoice.total_mismatch',
                'The invoice total including tax must equal the sum of the line totals (± 0.05).',
            ));
        }
        if (!$lines->totalExclTax()->isWithinTolerance($subject->totalExclTax(), $tolerance)) {
            $result = $result->with(Violation::warning(
                'totalExclTax',
                'invoice.total_excl_tax_mismatch',
                'The invoice total excluding tax differs from the sum of the line totals (± 0.05).',
            ));
        }

        return $result;
    }
}
