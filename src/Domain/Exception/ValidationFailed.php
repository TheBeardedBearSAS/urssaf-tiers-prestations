<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Exception;

use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;

/**
 * Thrown when a validation result containing errors must stop the current use case.
 */
final class ValidationFailed extends DomainException
{
    private function __construct(private readonly ValidationResult $result)
    {
        $errorCount = \count($result->errors());
        parent::__construct(
            \sprintf('Validation failed with %d error(s).', $errorCount),
            'validation.failed',
            ['error_count' => $errorCount],
        );
    }

    public static function withResult(ValidationResult $result): self
    {
        return new self($result);
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}
