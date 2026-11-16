<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use Psr\Clock\ClockInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Container factory of the clock of the package: the application "clock" service (PSR-20) when it exists, the UTC
 * system clock otherwise (symfony/clock is not required).
 */
final class ClockFactory
{
    public static function create(?ClockInterface $applicationClock): Clock
    {
        return $applicationClock instanceof ClockInterface ? new SystemClock($applicationClock) : new UtcSystemClock();
    }
}
