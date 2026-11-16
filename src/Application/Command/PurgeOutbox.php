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

use DateInterval;

/**
 * Deletes the outbox entries dispatched for longer than the retention.
 */
final readonly class PurgeOutbox
{
    public function __construct(public DateInterval $retention) {}
}
