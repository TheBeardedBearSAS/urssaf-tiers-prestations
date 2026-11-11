<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * The deposit must not exceed 50 % of the invoice total incl. tax; exactly 50 % is accepted (ERR_MONTANT_ACOMPTE).
 *
 * Violation: "deposit.exceeds_half" at "deposit.amount".
 *
 * @implements Validator<Invoice>
 */
final readonly class DepositWithinLimit implements Validator
{
    /**
     * @param Invoice $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if ($subject->deposit()?->exceedsHalfOf($subject->totalInclTax()) !== true) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'deposit.amount',
            'deposit.exceeds_half',
            'The deposit must not exceed 50 % of the invoice total including tax.',
        ));
    }
}
