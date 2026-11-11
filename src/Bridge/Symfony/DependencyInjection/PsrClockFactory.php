<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Container factory of the PSR-20 clock: the application "clock" service when it exists, the system clock otherwise.
 */
final class PsrClockFactory
{
    public static function create(?ClockInterface $applicationClock): ClockInterface
    {
        return $applicationClock ?? new NativeClock();
    }
}
