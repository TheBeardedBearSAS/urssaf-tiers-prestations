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
 * Settles the customer registrations (every tenant) left submitting for too long. Null limit: policy default page size.
 */
final readonly class ReconcileStaleRegistrations
{
    public function __construct(public ?int $limit) {}
}
