<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\ClockFactory;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UtcSystemClock;

final class ClockFactoryTest extends TestCase
{
    public function testTheClockOfTheApplicationIsUsedWhenThereIsOne(): void
    {
        $clock = new MockClock('2022-03-01 10:00:00 UTC');

        static::assertSame('2022-03-01T10:00:00+00:00', ClockFactory::create($clock)->now()->format(DATE_ATOM));
    }

    public function testTheUtcSystemClockIsTheFallback(): void
    {
        static::assertInstanceOf(UtcSystemClock::class, ClockFactory::create(null));
    }
}
