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

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Reads one customer registration of a tenant, by registration id or external reference.
 */
final readonly class GetCustomerRegistration
{
    public function __construct(public TenantId $tenantId, public CustomerSelector $customer) {}
}
