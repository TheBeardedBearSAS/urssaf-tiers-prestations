<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Outbox that writes each appended event type in the journal (to check that it happens inside the transaction).
 */
final readonly class JournalingOutbox implements Outbox
{
    public function __construct(private Journal $journal) {}

    public function append(IntegrationEvent ...$events): void
    {
        $this->journal->add('append:' . implode(',', array_map(static fn(IntegrationEvent $event): string => $event->type(), $events)));
    }

    public function pending(int $limit): array
    {
        return [];
    }

    public function markDispatched(OutboxEntry $entry, Instant $at): void {}

    public function markFailed(OutboxEntry $entry, string $error): void {}

    public function purgeDispatchedBefore(Instant $before): int
    {
        return 0;
    }
}
