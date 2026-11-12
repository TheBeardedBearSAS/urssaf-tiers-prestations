<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\RelayReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use Throwable;

/**
 * At-least-once delivery of the outbox: an event is marked as dispatched only once the sink accepted it. A failed event
 * stays pending, and the next events of the same record are not delivered in this run, so that the host receives the
 * events of a record in order. Runs must not overlap (the Symfony bridge takes a lock).
 */
final readonly class RelayOutboxHandler
{
    public function __construct(
        private Outbox $outbox,
        private IntegrationEventSink $sink,
        private Clock $clock,
        private WorkflowPolicy $policy,
    ) {}

    public function __invoke(RelayOutbox $command): RelayReport
    {
        $dispatched = 0;
        $failed = 0;
        $deferred = 0;
        $blocked = [];
        foreach ($this->outbox->pending($this->policy->pageSize($command->limit)) as $entry) {
            $record = $entry->event->aggregateId;
            if (\array_key_exists($record, $blocked)) {
                ++$deferred;

                continue;
            }
            try {
                $this->sink->dispatch($entry->event);
            } catch (Throwable $failure) {
                $this->outbox->markFailed($entry, $failure->getMessage());
                $blocked[$record] = $entry->sequence;
                ++$failed;

                continue;
            }
            $this->outbox->markDispatched($entry, Instant::fromDateTime($this->clock->now()));
            ++$dispatched;
        }

        return new RelayReport($dispatched, $failed, $deferred);
    }
}
