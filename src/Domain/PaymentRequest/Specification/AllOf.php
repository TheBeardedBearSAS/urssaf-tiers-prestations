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

use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;

/**
 * Composite: runs every validator and merges their results in order.
 *
 * @template T of object
 *
 * @implements Validator<T>
 */
final readonly class AllOf implements Validator
{
    /**
     * @param array<Validator<T>> $validators
     */
    private function __construct(private array $validators) {}

    /**
     * @template U of object
     *
     * @param Validator<U> ...$validators
     *
     * @return self<U>
     */
    public static function of(Validator ...$validators): self
    {
        return new self($validators);
    }

    public function validate(object $subject): ValidationResult
    {
        return array_reduce(
            $this->validators,
            static fn(ValidationResult $result, Validator $validator): ValidationResult => $result->merge(
                $validator->validate($subject),
            ),
            ValidationResult::valid(),
        );
    }
}
