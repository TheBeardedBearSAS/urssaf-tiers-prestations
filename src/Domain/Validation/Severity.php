<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Validation;

enum Severity: string
{
    /** Blocks the operation: URSSAF would reject the request. */
    case Error = 'error';

    /** Informative only: the operation may proceed. */
    case Warning = 'warning';
}
