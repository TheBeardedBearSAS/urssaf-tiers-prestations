<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message;

/**
 * Asks to poll the status of the submitted payment requests of one tenant (method 070).
 */
final readonly class SyncTenantPaymentStatusesMessage
{
    public function __construct(public string $tenantId) {}
}
