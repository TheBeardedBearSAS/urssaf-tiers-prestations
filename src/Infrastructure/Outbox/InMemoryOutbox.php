<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Outbox;

use Override;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Outbox kept in the process memory (tests, prototypes): not transactional, lost at the end of the process.
 */
final class InMemoryOutbox implements Outbox
{
    /** @var array<int, IntegrationEvent> by sequence */
    private array $events = [];

    /** @var array<int, int> failed attempts by sequence */
    private array $attempts = [];

    /** @var array<int, string> last failure by sequence */
    private array $errors = [];

    /** @var array<int, Instant> dispatch instant by sequence */
    private array $dispatchedAt = [];

    private int $nextSequence = 1;

    #[Override]
    public function append(IntegrationEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->events[$this->nextSequence++] = $event;
        }
    }

    #[Override]
    public function pending(int $limit): array
    {
        $pending = \array_slice(array_diff_key($this->events, $this->dispatchedAt), 0, $limit, true);

        return array_map(
            fn(int $sequence): OutboxEntry => new OutboxEntry($sequence, $pending[$sequence], $this->attempts[$sequence] ?? 0),
            array_keys($pending),
        );
    }

    #[Override]
    public function markDispatched(OutboxEntry $entry, Instant $at): void
    {
        $this->dispatchedAt[$entry->sequence] = $at;
    }

    #[Override]
    public function markFailed(OutboxEntry $entry, string $error): void
    {
        $this->attempts[$entry->sequence] = ($this->attempts[$entry->sequence] ?? 0) + 1;
        $this->errors[$entry->sequence] = $error;
    }

    #[Override]
    public function purgeDispatchedBefore(Instant $before): int
    {
        $purged = array_filter($this->dispatchedAt, static fn(Instant $at): bool => $at->isBefore($before));
        $this->events = array_diff_key($this->events, $purged);
        $this->dispatchedAt = array_diff_key($this->dispatchedAt, $purged);

        return \count($purged);
    }

    /**
     * The message of the last failed delivery of the entry (tests).
     */
    public function lastError(int $sequence): ?string
    {
        return $this->errors[$sequence] ?? null;
    }
}
