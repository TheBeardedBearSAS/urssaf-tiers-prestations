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
 * Outcome of a batch use case for one record: the record identifier (registration id, local payment request id,
 * URSSAF id for an ignored item, tenant id for a tenant-wide failure) and the related URSSAF codes.
 */
final readonly class BatchItem
{
    /**
     * @param list<string> $codes
     */
    public function __construct(
        public string $subject,
        public ItemOutcome $outcome,
        public array $codes = [],
    ) {}
}
