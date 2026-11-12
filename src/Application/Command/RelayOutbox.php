<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

/**
 * Delivers the pending integration events of the outbox, in recording order.
 */
final readonly class RelayOutbox
{
    /**
     * @param ?int $limit events read at most (null: the default page size of the workflow policy, capped by its maximum)
     */
    public function __construct(public ?int $limit) {}
}
