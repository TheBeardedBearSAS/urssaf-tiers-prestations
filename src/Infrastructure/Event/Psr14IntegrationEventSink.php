<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;

/**
 * Delivers the integration events to the listeners of a PSR-14 dispatcher (in the relay process): a listener that
 * throws keeps the event in the outbox.
 */
final readonly class Psr14IntegrationEventSink implements IntegrationEventSink
{
    public function __construct(private EventDispatcherInterface $dispatcher) {}

    #[Override]
    public function dispatch(IntegrationEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
