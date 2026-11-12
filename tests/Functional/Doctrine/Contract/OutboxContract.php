<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\CustomerRegistrationRejected;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Behaviour shared by every Outbox adapter.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 */
trait OutboxContract
{
    abstract protected function createOutbox(): Outbox;

    public function testTheAppendedEventsArePendingInRecordingOrder(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::statusEvent('e1'), self::rejected('e2'));
        $outbox->append(self::statusEvent('e3'));

        $pending = $outbox->pending(10);

        static::assertSame(['e1', 'e2', 'e3'], self::ids($pending));
        static::assertEquals(self::rejected('e2'), $pending[1]->event);
        static::assertSame([0, 0, 0], array_map(static fn(OutboxEntry $entry): int => $entry->attempts, $pending));
        static::assertLessThan($pending[1]->sequence, $pending[0]->sequence);
        static::assertLessThan($pending[2]->sequence, $pending[1]->sequence);
        static::assertSame(['e1', 'e2'], self::ids($outbox->pending(2)));
    }

    public function testADispatchedEntryIsNoLongerPending(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::statusEvent('e1'), self::statusEvent('e2'));

        $outbox->markDispatched($outbox->pending(1)[0], Instant::fromIso8601('2022-03-01T10:00:00Z'));

        static::assertSame(['e2'], self::ids($outbox->pending(10)));
    }

    public function testAFailedEntryStaysPendingWithOneMoreAttempt(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::statusEvent('e1'));

        $outbox->markFailed($outbox->pending(1)[0], 'Broker down.');
        $outbox->markFailed($outbox->pending(1)[0], 'Broker still down.');

        $pending = $outbox->pending(10);
        static::assertSame(['e1'], self::ids($pending));
        static::assertSame(2, $pending[0]->attempts);
    }

    public function testThePurgeDeletesTheEntriesDispatchedBeforeTheInstantOnly(): void
    {
        $outbox = $this->createOutbox();
        $outbox->append(self::statusEvent('e1'), self::statusEvent('e2'), self::statusEvent('e3'));
        [$first, $second] = $outbox->pending(2);
        $outbox->markDispatched($first, Instant::fromIso8601('2022-03-01T10:00:00Z'));
        $outbox->markDispatched($second, Instant::fromIso8601('2022-03-02T10:00:00Z'));

        static::assertSame(0, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2022-03-01T10:00:00Z')));
        static::assertSame(1, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2022-03-02T10:00:00Z')));
        static::assertSame(1, $outbox->purgeDispatchedBefore(Instant::fromIso8601('2022-03-03T10:00:00Z')));
        static::assertSame(['e3'], self::ids($outbox->pending(10)));
    }

    private static function statusEvent(string $eventId): PaymentRequestStatusChanged
    {
        return new PaymentRequestStatusChanged($eventId, '2022-03-01T10:00:00Z', 'tenant-1', 'local-1', 'invoice-42', null, '10', false, false);
    }

    private static function rejected(string $eventId): CustomerRegistrationRejected
    {
        return new CustomerRegistrationRejected($eventId, '2022-03-01T10:00:00Z', 'tenant-1', 'reg-1', 'customer-1', ['ERR_DGFIP_R2P_INCONNU']);
    }

    /**
     * @param list<OutboxEntry> $entries
     *
     * @return list<string>
     */
    private static function ids(array $entries): array
    {
        return array_map(static fn(OutboxEntry $entry): string => $entry->event->eventId, $entries);
    }
}
