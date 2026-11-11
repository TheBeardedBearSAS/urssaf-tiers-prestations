<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Quantity x unit price must equal the line total incl. tax within ± 0.05, bounds included (ERR_MNT_PREST_TTC).
 *
 * Violation: "line.total_mismatch" at "amounts.totalInclTax".
 *
 * @implements Validator<ServiceLine>
 */
final readonly class LineTotalMatchesUnitPrice implements Validator
{
    /**
     * @param ServiceLine $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if ($subject->unitPrice()->times($subject->quantity())->isWithinTolerance(
            $subject->amounts()->totalInclTax(),
            Money::fromCents(Money::URSSAF_TOLERANCE_CENTS),
        )) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'amounts.totalInclTax',
            'line.total_mismatch',
            'The line total including tax must equal the quantity times the unit price (± 0.05).',
        ));
    }
}
