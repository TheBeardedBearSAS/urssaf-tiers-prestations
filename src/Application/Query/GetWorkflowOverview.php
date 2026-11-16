<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Query;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Counts of the records by state, for every tenant that has records, or for one tenant.
 */
final readonly class GetWorkflowOverview
{
    private function __construct(public ?TenantId $tenantId) {}

    public static function allTenants(): self
    {
        return new self(null);
    }

    public static function forTenant(TenantId $tenantId): self
    {
        return new self($tenantId);
    }
}
