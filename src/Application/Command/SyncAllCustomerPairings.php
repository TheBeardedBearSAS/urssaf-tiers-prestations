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

/**
 * Runs SyncCustomerPairing for every tenant having registrations awaiting the pairing.
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SyncAllCustomerPairings
{
    public function __construct(public ?int $limitPerTenant) {}
}
