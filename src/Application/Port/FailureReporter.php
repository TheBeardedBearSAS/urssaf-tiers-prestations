<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use Throwable;

/**
 * Receives the failures that must not break a use case once its state change is committed (e.g. log them).
 */
interface FailureReporter
{
    /**
     * The transaction was committed but the {@see EventPublisher} failed: the state is saved, some listeners may have
     * missed these events. Must not throw.
     *
     * @param list<DomainEvent> $events the events of the committed transaction, in release order
     */
    public function eventPublicationFailed(Throwable $failure, array $events): void;
}
