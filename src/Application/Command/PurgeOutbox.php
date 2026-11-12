<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use DateInterval;

/**
 * Deletes the outbox entries dispatched for longer than the retention.
 */
final readonly class PurgeOutbox
{
    public function __construct(public DateInterval $retention) {}
}
