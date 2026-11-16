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
 * Asks to poll the pairing status of the customers of one tenant (method 020).
 */
final readonly class SyncTenantCustomerPairingMessage
{
    public function __construct(public string $tenantId) {}
}
