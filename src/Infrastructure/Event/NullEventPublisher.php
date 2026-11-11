<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;

/**
 * Event port discarding every event (no event bus configured).
 */
final class NullEventPublisher implements EventPublisher
{
    public function publish(DomainEvent ...$events): void {}
}
