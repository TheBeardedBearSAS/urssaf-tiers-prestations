<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\FrozenClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;

final class RelayOutboxHandlerTest extends TestCase
{
    private const NOW = '2022-03-01T10:00:00Z';

    public function testThePendingEventsAreDeliveredInOrderThenMarkedAsDispatched(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'a']);
        $sink = new RecordingIntegrationEventSink();

        $report = self::handler($outbox, $sink)(new RelayOutbox(null));

        static::assertSame(['e1', 'e2', 'e3'], $sink->deliveredIds());
        static::assertSame([3, 0, 0], [$report->dispatched, $report->failed, $report->deferred]);
        static::assertSame([], $outbox->pending(10));
        static::assertSame(3, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2022-03-01T10:00:01Z')));
    }

    public function testAFailedEventStaysPendingAndHoldsBackTheNextEventsOfItsRecordOnly(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'a'], ['e4', 'c']);
        $sink = new RecordingIntegrationEventSink(['a']);

        $report = self::handler($outbox, $sink)(new RelayOutbox(null));

        static::assertSame(['e2', 'e4'], $sink->deliveredIds());
        static::assertSame([2, 1, 1], [$report->dispatched, $report->failed, $report->deferred]);
        $pending = $outbox->pending(10);
        static::assertSame([[1, 1], [3, 0]], array_map(static fn(OutboxEntry $entry): array => [$entry->sequence, $entry->attempts], $pending));
        static::assertSame('Broker down for a.', $outbox->lastError(1));
        static::assertNull($outbox->lastError(3));
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $outbox = self::outbox(['e1', 'a'], ['e2', 'b'], ['e3', 'c']);
        $sink = new RecordingIntegrationEventSink();
        $handler = new RelayOutboxHandler($outbox, $sink, self::clock(), new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P90D'), new DateInterval('PT15M'), 'menage.fr', 10, 2, 2));

        $handler(new RelayOutbox(null));
        static::assertSame(['e1', 'e2'], $sink->deliveredIds());
        $handler(new RelayOutbox(1));
        static::assertSame(['e1', 'e2', 'e3'], $sink->deliveredIds());
    }

    public function testThePurgeKeepsTheEntriesDispatchedWithinTheRetention(): void
    {
        $outbox = self::outbox(['e1', 'a']);
        self::handler($outbox, new RecordingIntegrationEventSink())(new RelayOutbox(null));
        $purge = new PurgeOutboxHandler($outbox, new FrozenClock(new DateTimeImmutable('2022-03-31T10:00:00Z')));

        static::assertSame(0, $purge(new PurgeOutbox(new DateInterval('P30D'))));
        static::assertSame(1, $purge(new PurgeOutbox(new DateInterval('P29DT23H59M59S'))));
    }

    /**
     * @param array{string, string} ...$events event id and aggregate id of each event
     */
    private static function outbox(array ...$events): InMemoryOutbox
    {
        $outbox = new InMemoryOutbox();
        foreach ($events as [$eventId, $aggregateId]) {
            $outbox->append(new PaymentRequestSubmitted($eventId, self::NOW, 'tenant-1', $aggregateId, 'ref-' . $aggregateId, 'urssaf-' . $aggregateId));
        }

        return $outbox;
    }

    private static function handler(InMemoryOutbox $outbox, RecordingIntegrationEventSink $sink): RelayOutboxHandler
    {
        return new RelayOutboxHandler($outbox, $sink, self::clock(), new WorkflowPolicy());
    }

    private static function clock(): FrozenClock
    {
        return new FrozenClock(new DateTimeImmutable(self::NOW));
    }
}
