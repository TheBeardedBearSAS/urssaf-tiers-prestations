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

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Moves the registrations and payment requests refused for invalid credentials back to pending (once the tenant credentials are fixed).
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class RequeueAfterInvalidCredentials
{
    public function __construct(public TenantId $tenantId, public ?int $limit) {}
}
