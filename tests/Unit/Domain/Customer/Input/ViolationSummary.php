<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

final class ViolationSummary
{
    /**
     * @return list<string> "path: code" of every violation, in order
     */
    public static function of(ValidationResult $result): array
    {
        return array_map(
            static fn(Violation $violation): string => $violation->path() . ': ' . $violation->code(),
            $result->all(),
        );
    }
}
