<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Testing;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * A customer registered with the {@see SimulatedUrssafGateway}: each poll returns the next pairing status, the last one
 * is repeated.
 *
 * @internal
 */
final class SimulatedCustomer
{
    /**
     * @param array<PairingStatus> $next
     */
    public function __construct(
        public readonly UrssafCustomerId $id,
        public readonly TenantId $tenantId,
        private PairingStatus $current,
        private array $next,
    ) {}

    public function poll(): PairingStatus
    {
        $status = $this->current;
        $this->current = array_shift($this->next) ?? $this->current;

        return $status;
    }

    public function force(PairingStatus $status): void
    {
        $this->current = $status;
        $this->next = [];
    }
}
