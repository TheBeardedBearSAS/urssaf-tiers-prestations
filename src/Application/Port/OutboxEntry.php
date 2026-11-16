<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;

/**
 * An integration event waiting in the outbox.
 */
final readonly class OutboxEntry
{
    /**
     * @param int $sequence position in the outbox (recording order)
     * @param int $attempts failed dispatch attempts so far
     */
    public function __construct(public int $sequence, public IntegrationEvent $event, public int $attempts) {}
}
