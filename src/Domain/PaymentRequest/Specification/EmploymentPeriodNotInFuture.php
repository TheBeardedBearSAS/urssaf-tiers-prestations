<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * The employment period must be over: its end must not be after now (ERR_DATE_FUTUR). Ending exactly now is accepted.
 *
 * Violation: "employment_period.in_future" at "employmentPeriod".
 *
 * @implements Validator<Invoice>
 */
final readonly class EmploymentPeriodNotInFuture implements Validator
{
    public function __construct(private Instant $now) {}

    /**
     * @param Invoice $subject
     */
    public function validate(object $subject): ValidationResult
    {
        if (!$subject->employmentPeriod()->isInFuture($this->now)) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'employmentPeriod',
            'employment_period.in_future',
            'The employment period must not end in the future.',
        ));
    }
}
