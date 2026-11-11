<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf;

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;

/**
 * Default clock of {@see UrssafGatewayFactory} when none is given: the current system time, in UTC.
 *
 * @internal
 */
final readonly class UtcSystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
