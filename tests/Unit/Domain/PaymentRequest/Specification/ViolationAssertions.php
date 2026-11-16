<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Compact representation of a validation result for assertions: "<severity> <path> <code>".
 */
final class ViolationAssertions
{
    /**
     * @return list<string>
     */
    public static function summary(ValidationResult $result): array
    {
        return array_map(
            static fn(Violation $violation): string => \sprintf(
                '%s %s %s',
                $violation->severity()->value,
                $violation->path(),
                $violation->code(),
            ),
            $result->all(),
        );
    }
}
