<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Event;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\InMemoryEventPublisher;

final class InMemoryEventPublisherTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        static::assertSame([], (new InMemoryEventPublisher())->published());
    }

    public function testRecordsEventsInPublicationOrderAcrossCalls(): void
    {
        $publisher = new InMemoryEventPublisher();
        $first = new FakeDomainEvent('first', new DateTimeImmutable('2026-01-01T10:00:00+00:00'));
        $second = new FakeDomainEvent('second', new DateTimeImmutable('2026-01-01T10:00:01+00:00'));
        $third = new FakeDomainEvent('third', new DateTimeImmutable('2026-01-01T10:00:02+00:00'));

        $publisher->publish($first, $second);
        $publisher->publish();
        $publisher->publish($third);

        static::assertSame([$first, $second, $third], $publisher->published());
    }
}
