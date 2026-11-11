<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Informs that a "miscellaneous" line (270) gives right neither to the tax credit nor to the aids. Accepted by URSSAF.
 *
 * Violation (warning): "line.not_eligible" at "natureCode".
 *
 * @implements Validator<ServiceLine>
 */
final readonly class NatureIsEligibleForTaxCredit implements Validator
{
    /**
     * @param ServiceLine $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if ($subject->natureCode()->isEligibleForTaxCredit()) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::warning(
            'natureCode',
            'line.not_eligible',
            'This nature of service is eligible neither to the tax credit nor to the aids.',
        ));
    }
}
