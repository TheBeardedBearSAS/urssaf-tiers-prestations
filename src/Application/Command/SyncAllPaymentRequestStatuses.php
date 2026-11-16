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
 * Runs SyncPaymentRequestStatuses for every tenant having pollable payment requests.
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SyncAllPaymentRequestStatuses
{
    public function __construct(public ?int $limitPerTenant) {}
}
