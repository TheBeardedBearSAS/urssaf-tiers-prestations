<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

final readonly class PurgeOutboxHandler
{
    public function __construct(private Outbox $outbox, private Clock $clock) {}

    /**
     * @return int the number of deleted entries
     */
    public function __invoke(PurgeOutbox $command): int
    {
        return $this->outbox->purgeDispatchedBefore(Instant::fromDateTime($this->clock->now()->sub($command->retention)));
    }
}
