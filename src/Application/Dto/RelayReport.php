<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

/**
 * Outcome of one outbox relay run.
 */
final readonly class RelayReport
{
    /**
     * @param int $dispatched events delivered and marked as dispatched
     * @param int $failed     events whose delivery failed (kept pending)
     * @param int $deferred   events left pending because an earlier event of the same record failed (order kept)
     */
    public function __construct(public int $dispatched, public int $failed, public int $deferred) {}
}
