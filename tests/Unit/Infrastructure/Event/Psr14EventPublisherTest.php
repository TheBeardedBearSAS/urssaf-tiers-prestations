<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Event;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\Psr14EventPublisher;

final class Psr14EventPublisherTest extends TestCase
{
    public function testDispatchesEveryEventInOrder(): void
    {
        $dispatcher = new RecordingEventDispatcher();
        $first = new FakeDomainEvent('first', new DateTimeImmutable('2026-01-01T10:00:00+00:00'));
        $second = new FakeDomainEvent('second', new DateTimeImmutable('2026-01-01T10:00:01+00:00'));

        (new Psr14EventPublisher($dispatcher))->publish($first, $second);

        static::assertSame([$first, $second], $dispatcher->dispatched());
    }

    public function testDispatchesNothingWhenThereAreNoEvents(): void
    {
        $dispatcher = new RecordingEventDispatcher();

        (new Psr14EventPublisher($dispatcher))->publish();

        static::assertSame([], $dispatcher->dispatched());
    }
}
