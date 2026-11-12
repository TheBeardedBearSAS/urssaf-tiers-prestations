<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;

/**
 * Records the delivered events; refuses the events of the given records.
 */
final class RecordingIntegrationEventSink implements IntegrationEventSink
{
    /** @var list<IntegrationEvent> */
    private array $delivered = [];

    /**
     * @param list<string> $refusedAggregates
     */
    public function __construct(private readonly array $refusedAggregates = []) {}

    public function dispatch(IntegrationEvent $event): void
    {
        if (\in_array($event->aggregateId, $this->refusedAggregates, true)) {
            throw new RuntimeException('Broker down for ' . $event->aggregateId . '.');
        }
        $this->delivered[] = $event;
    }

    /**
     * @return list<string> the ids of the delivered events, in order
     */
    public function deliveredIds(): array
    {
        return array_map(static fn(IntegrationEvent $event): string => $event->eventId, $this->delivered);
    }
}
