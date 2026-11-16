<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Timeline;

final class TimelineTest extends TestCase
{
    public function testStartedAt(): void
    {
        $timeline = Timeline::startedAt(Fixtures::at(Fixtures::NOW));

        static::assertSame(Fixtures::NOW, $timeline->createdAt()->toIso8601());
        static::assertSame(Fixtures::NOW, $timeline->updatedAt()->toIso8601());
        static::assertNull($timeline->submissionStartedAt());
        static::assertNull($timeline->submittedAt());
        static::assertFalse($timeline->isSubmissionStartedBefore(Fixtures::at('2030-01-01T00:00:00Z'), new DateInterval('PT1S')));
    }

    public function testChanges(): void
    {
        $timeline = Timeline::startedAt(Fixtures::at(Fixtures::NOW))
            ->submissionStarted(Fixtures::at('2022-03-01T00:00:01Z'))
            ->submitted(Fixtures::at('2022-03-01T00:00:02Z'))
            ->touched(Fixtures::at('2022-03-01T00:00:03Z'));

        static::assertSame(Fixtures::NOW, $timeline->createdAt()->toIso8601());
        static::assertSame('2022-03-01T00:00:03Z', $timeline->updatedAt()->toIso8601());
        static::assertSame('2022-03-01T00:00:01Z', $timeline->submissionStartedAt()?->toIso8601());
        static::assertSame('2022-03-01T00:00:02Z', $timeline->submittedAt()?->toIso8601());
    }

    public function testSubmissionStartedAgainKeepsTheSubmittedInstant(): void
    {
        $timeline = Timeline::startedAt(Fixtures::at(Fixtures::NOW))
            ->submitted(Fixtures::at('2022-03-01T00:00:02Z'))
            ->submissionStarted(Fixtures::at('2022-03-01T00:00:05Z'));

        static::assertSame('2022-03-01T00:00:02Z', $timeline->submittedAt()?->toIso8601());
        static::assertSame('2022-03-01T00:00:05Z', $timeline->updatedAt()->toIso8601());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAges(): iterable
    {
        yield 'one second before the deadline' => ['2022-03-01T00:14:59Z', false];
        yield 'at the deadline' => ['2022-03-01T00:15:00Z', true];
        yield 'after the deadline' => ['2022-03-01T00:15:01Z', true];
    }

    #[DataProvider('provideAges')]
    public function testIsSubmissionStartedBefore(string $now, bool $expected): void
    {
        $timeline = Timeline::startedAt(Fixtures::at(Fixtures::NOW))->submissionStarted(Fixtures::at(Fixtures::NOW));

        static::assertSame($expected, $timeline->isSubmissionStartedBefore(Fixtures::at($now), new DateInterval('PT15M')));
    }
}
