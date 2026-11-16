<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use DateTimeImmutable;

/**
 * Marker for every event recorded by an aggregate.
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
