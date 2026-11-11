<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TransactionManager;
use Throwable;

/**
 * Runs a state change in a transaction, then publishes the domain events the operation recorded, only once the
 * transaction is committed (nothing is published when the operation or the commit fails).
 *
 * Publication is best effort: once the transaction is committed, a failure of the publisher must not make the use
 * case fail (the state is saved, a retry would find nothing to do and the remaining records of a batch would not be
 * processed). It is caught and handed to the {@see FailureReporter} ({@see IgnoredFailures} by default: configure one that logs), and the result
 * of the operation is returned. Hosts that need guaranteed delivery publish from an outbox written in the same
 * transaction (e.g. an EventPublisher that stores the events in the host database).
 */
final readonly class UnitOfWork
{
    public function __construct(
        private TransactionManager $transactions,
        private EventPublisher $publisher,
        private FailureReporter $failureReporter = new IgnoredFailures(),
    ) {}

    /**
     * @template T
     *
     * @param callable(RecordedEvents): T $operation saves the aggregates and records their released events
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed
    {
        $events = new RecordedEvents();
        $result = $this->transactions->transactional(static fn() => $operation($events));

        try {
            $this->publisher->publish(...$events->all());
        } catch (Throwable $failure) {
            $this->failureReporter->eventPublicationFailed($failure, $events->all());
        }

        return $result;
    }
}
