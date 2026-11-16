<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input;

/**
 * Raw employment period: ISO 8601 date-times with an explicit offset (e.g. "2022-02-01T16:40:00Z").
 */
final readonly class PeriodDraft
{
    public function __construct(public ?string $start, public ?string $end) {}
}
