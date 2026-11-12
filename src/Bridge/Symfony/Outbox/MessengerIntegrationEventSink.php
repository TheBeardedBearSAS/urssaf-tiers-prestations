<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Outbox;

use Override;
use Symfony\Component\Messenger\MessageBusInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;

/**
 * Dispatches the integration events on a Messenger bus: route them to a transport of your own and handle them with
 * message handlers (#[AsMessageHandler]).
 */
final readonly class MessengerIntegrationEventSink implements IntegrationEventSink
{
    public function __construct(private MessageBusInterface $bus) {}

    #[Override]
    public function dispatch(IntegrationEvent $event): void
    {
        $this->bus->dispatch($event);
    }
}
