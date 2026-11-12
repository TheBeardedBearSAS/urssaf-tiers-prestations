<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use Throwable;

/**
 * Where the outbox relay delivers the integration events (a message bus, an event dispatcher...).
 */
interface IntegrationEventSink
{
    /**
     * @throws Throwable when the event could not be delivered: it stays in the outbox and is relayed again later
     */
    public function dispatch(IntegrationEvent $event): void;
}
