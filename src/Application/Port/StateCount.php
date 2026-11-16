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

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Number of records of one tenant in one state.
 */
final readonly class StateCount
{
    /**
     * @param string $state the value of a RegistrationState or a SubmissionState
     */
    public function __construct(public TenantId $tenantId, public string $state, public int $count) {}
}
