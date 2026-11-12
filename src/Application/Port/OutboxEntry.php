<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;

/**
 * An integration event waiting in the outbox.
 */
final readonly class OutboxEntry
{
    /**
     * @param int $sequence position in the outbox (recording order)
     * @param int $attempts failed dispatch attempts so far
     */
    public function __construct(public int $sequence, public IntegrationEvent $event, public int $attempts) {}
}
