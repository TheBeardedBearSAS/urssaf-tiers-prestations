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
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * The optional period of each line lies within the invoice employment period, bounds included (the invoice period
 * runs from the start of the first service to the end of the last one).
 *
 * Violation: "line.period_outside_invoice_period" at "lines[i].period".
 *
 * @implements Validator<Invoice>
 */
final readonly class LinesPeriodsWithinInvoicePeriod implements Validator
{
    /**
     * @param Invoice $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $result = ValidationResult::valid();
        foreach ($subject->lines()->all() as $index => $line) {
            $period = $line->period();
            if ($period !== null && !$subject->employmentPeriod()->contains($period)) {
                $result = $result->with(Violation::error(
                    \sprintf('lines[%d].period', $index),
                    'line.period_outside_invoice_period',
                    'The line period must lie within the employment period of the payment request.',
                ));
            }
        }

        return $result;
    }
}
