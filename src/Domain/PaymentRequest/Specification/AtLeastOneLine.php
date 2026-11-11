<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * A payment request has at least one service line (inputPrestations).
 *
 * Violation: "lines.empty" at "lines".
 *
 * @implements Validator<Invoice>
 */
final readonly class AtLeastOneLine implements Validator
{
    /**
     * @param Invoice $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if (!$subject->lines()->isEmpty()) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error('lines', 'lines.empty', 'At least one service line is required.'));
    }
}
