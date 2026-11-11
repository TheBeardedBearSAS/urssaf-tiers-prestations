<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Validation;

/**
 * Validates a subject against the URSSAF rules and reports every finding instead of failing fast.
 *
 * @template T of object
 */
interface Validator
{
    /**
     * @param T $subject
     */
    public function validate(object $subject): ValidationResult;
}
