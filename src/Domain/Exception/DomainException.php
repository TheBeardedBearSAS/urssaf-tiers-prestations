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

use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Base class of every domain exception. Each one carries a stable snake_case violation code
 * (e.g. "money.negative") and scalar parameters, and can be converted into a validation violation.
 *
 * Messages are English and never contain secrets nor the rejected raw value.
 */
abstract class DomainException extends \DomainException implements UrssafException
{
    /**
     * @param array<string, scalar> $params
     */
    protected function __construct(string $message, private readonly string $violationCode, private readonly array $params)
    {
        parent::__construct($message);
    }

    final public function violationCode(): string
    {
        return $this->violationCode;
    }

    /**
     * @return array<string, scalar>
     */
    final public function params(): array
    {
        return $this->params;
    }

    /**
     * Converts the exception into an error violation located at the given path (empty path = root).
     */
    final public function toViolation(string $path): Violation
    {
        return Violation::error($path, $this->violationCode, $this->getMessage(), $this->params);
    }
}
