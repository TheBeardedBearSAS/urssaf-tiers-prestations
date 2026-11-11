<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;

/**
 * Deterministic clock starting on 2024-01-01T10:00:00Z: every now() call returns the current instant then advances
 * it by a fixed step.
 */
final class SteppingClock implements Clock
{
    private const START_TIMESTAMP = 1_704_103_200;
    private const MICROSECONDS_PER_SECOND = 1_000_000;

    private int $elapsedMicroseconds = 0;

    public function __construct(private readonly int $stepMicroseconds) {}

    public function now(): DateTimeImmutable
    {
        $now = new DateTimeImmutable(\sprintf(
            '@%d.%06d',
            self::START_TIMESTAMP + intdiv($this->elapsedMicroseconds, self::MICROSECONDS_PER_SECOND),
            $this->elapsedMicroseconds % self::MICROSECONDS_PER_SECOND,
        ));
        $this->elapsedMicroseconds += $this->stepMicroseconds;

        return $now;
    }

    public function advanceSeconds(int $seconds): void
    {
        $this->elapsedMicroseconds += $seconds * self::MICROSECONDS_PER_SECOND;
    }
}
