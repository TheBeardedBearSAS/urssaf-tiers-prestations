<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TransactionManager;
use Throwable;

/**
 * Runs a state change in a transaction, then publishes the domain events the operation recorded, only once the
 * transaction is committed (nothing is published when the operation or the commit fails).
 *
 * Publication is best effort: once the transaction is committed, a failure of the publisher must not make the use
 * case fail (the state is saved, a retry would find nothing to do and the remaining records of a batch would not be
 * processed). It is caught and handed to the {@see FailureReporter} ({@see IgnoredFailures} by default: configure one that logs), and the result
 * of the operation is returned.
 *
 * Guaranteed delivery: the {@see Outbox} receives the integration events of the recorded domain events inside the
 * transaction, so that they are durable with the state change ({@see IgnoredOutbox} by default); the outbox relay
 * delivers them afterwards.
 */
final readonly class UnitOfWork
{
    public function __construct(
        private TransactionManager $transactions,
        private EventPublisher $publisher,
        private FailureReporter $failureReporter = new IgnoredFailures(),
        private Outbox $outbox = new IgnoredOutbox(),
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
        $result = $this->transactions->transactional(function () use ($operation, $events): mixed {
            $result = $operation($events);
            $this->outbox->append(...IntegrationEventTranslator::translate(...$events->all()));

            return $result;
        });

        try {
            $this->publisher->publish(...$events->all());
        } catch (Throwable $failure) {
            $this->failureReporter->eventPublicationFailed($failure, $events->all());
        }

        return $result;
    }
}
