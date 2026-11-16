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
 * Submits the oldest pending payment requests of a tenant (see SubmitPaymentRequests).
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SubmitPendingPaymentRequests
{
    public function __construct(public TenantId $tenantId, public ?int $limit) {}
}
