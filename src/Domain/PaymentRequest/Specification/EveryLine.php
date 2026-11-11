<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;

/**
 * Applies a line validator to every line of the invoice, nesting the violations under "lines[i]".
 *
 * @implements Validator<Invoice>
 */
final readonly class EveryLine implements Validator
{
    /**
     * @param Validator<ServiceLine> $lineValidator
     */
    public function __construct(private Validator $lineValidator) {}

    /**
     * @param Invoice $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $result = ValidationResult::valid();
        foreach ($subject->lines()->all() as $index => $line) {
            $result = $result->merge($this->lineValidator->validate($line)->withPathPrefix(\sprintf('lines[%d]', $index)));
        }

        return $result;
    }
}
