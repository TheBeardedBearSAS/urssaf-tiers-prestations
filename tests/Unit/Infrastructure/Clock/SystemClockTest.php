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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\SystemClock;

final class SystemClockTest extends TestCase
{
    public function testDelegatesToThePsrClock(): void
    {
        $psrClock = new MockClock('2026-01-01T10:00:00+00:00');
        $clock = new SystemClock($psrClock);

        static::assertSame('2026-01-01T10:00:00+00:00', $clock->now()->format(DATE_ATOM));

        $psrClock->modify('+1 hour');

        static::assertSame('2026-01-01T11:00:00+00:00', $clock->now()->format(DATE_ATOM));
    }
}
