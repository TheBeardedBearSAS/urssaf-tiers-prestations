<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock;

use DateInterval;
use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;

/**
 * Clock port returning a fixed instant, for deterministic tests and replays. Immutable: advancing returns a new clock.
 */
final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(DateInterval $interval): self
    {
        return new self($this->now->add($interval));
    }
}
