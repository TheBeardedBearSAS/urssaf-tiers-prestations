<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Clock;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\FrozenClock;

final class FrozenClockTest extends TestCase
{
    public function testReturnsTheFixedInstant(): void
    {
        $now = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $clock = new FrozenClock($now);

        static::assertSame($now, $clock->now());
        static::assertSame($now, $clock->now());
    }

    public function testAdvanceReturnsANewClock(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T10:00:00+00:00'));

        $advanced = $clock->advance(new DateInterval('PT90S'));

        static::assertNotSame($clock, $advanced);
        static::assertSame('2026-01-01T10:01:30+00:00', $advanced->now()->format(DATE_ATOM));
    }

    public function testAdvanceLeavesTheOriginalClockUnchanged(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T10:00:00+00:00'));

        $clock->advance(new DateInterval('P1D'));

        static::assertSame('2026-01-01T10:00:00+00:00', $clock->now()->format(DATE_ATOM));
    }
}
