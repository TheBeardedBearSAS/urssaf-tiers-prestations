<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

/**
 * Number of records moved back from invalid_credentials to pending.
 */
final readonly class RequeueReport
{
    public function __construct(public int $registrations, public int $paymentRequests) {}
}
