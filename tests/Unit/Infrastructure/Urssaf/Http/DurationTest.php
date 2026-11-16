<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Http;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\Duration;

final class DurationTest extends TestCase
{
    public function testItComputesWholeMillisecondsAcrossSeconds(): void
    {
        $start = new DateTimeImmutable('2024-01-01T10:00:00.900000+00:00');
        $end = new DateTimeImmutable('2024-01-01T10:00:02.150900+00:00');

        static::assertSame(1250, Duration::millisecondsSince($start, $end));
    }

    public function testItIsZeroForTheSameInstant(): void
    {
        $instant = new DateTimeImmutable('2024-01-01T10:00:00+00:00');

        static::assertSame(0, Duration::millisecondsSince($instant, $instant));
    }
}
