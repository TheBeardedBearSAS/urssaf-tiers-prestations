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

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;

interface EventPublisher
{
    /**
     * Publishes the events in the given order. Called by handlers after the transaction is committed.
     */
    public function publish(DomainEvent ...$events): void;
}
