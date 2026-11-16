<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

final class EmploymentPeriodQueriesTest extends TestCase
{
    private static function period(string $start, string $end, string $timeZone = 'Europe/Paris'): EmploymentPeriod
    {
        return EmploymentPeriod::between(Instant::fromIso8601($start), Instant::fromIso8601($end), new DateTimeZone($timeZone));
    }

    public function testBusinessDates(): void
    {
        $period = self::period('2022-01-31T23:10:00Z', '2022-01-31T23:20:00Z', 'Asia/Tokyo');

        static::assertSame('2022-02-01', $period->startDate()->toString());
        static::assertSame('2022-02-01', $period->endDate()->toString());

        $other = self::period('2022-03-01T08:00:00Z', '2022-03-31T08:00:00Z', 'UTC');
        static::assertSame('2022-03-01', $other->startDate()->toString());
        static::assertSame('2022-03-31', $other->endDate()->toString());
    }

    public function testStartsBefore(): void
    {
        $period = self::period('2022-02-01T16:40:00Z', '2022-02-01T16:45:00Z');

        static::assertTrue($period->startsBefore(Instant::fromIso8601('2022-02-01T16:40:01Z')));
        static::assertFalse($period->startsBefore(Instant::fromIso8601('2022-02-01T16:40:00Z')));
        static::assertFalse($period->startsBefore(Instant::fromIso8601('2022-02-01T16:39:59Z')));
    }

    public function testIsInFuture(): void
    {
        $period = self::period('2022-02-01T16:40:00Z', '2022-02-01T16:45:00Z');

        static::assertTrue($period->isInFuture(Instant::fromIso8601('2022-02-01T16:44:59Z')));
        static::assertFalse($period->isInFuture(Instant::fromIso8601('2022-02-01T16:45:00Z')));
        static::assertFalse($period->isInFuture(Instant::fromIso8601('2022-02-01T16:45:01Z')));
    }

    public function testContains(): void
    {
        $period = self::period('2022-02-01T08:00:00Z', '2022-02-20T18:00:00Z');

        static::assertTrue($period->contains($period));
        static::assertTrue($period->contains(self::period('2022-02-05T08:00:00Z', '2022-02-06T08:00:00Z')));
        static::assertFalse($period->contains(self::period('2022-02-01T07:59:59Z', '2022-02-06T08:00:00Z')));
        static::assertFalse($period->contains(self::period('2022-02-05T08:00:00Z', '2022-02-20T18:00:01Z')));
    }
}
