<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

final class RecordingRateLimiter implements RateLimiter
{
    /** @var list<string> */
    private array $acquired = [];

    public function acquire(TenantId $tenantId, Operation $operation): void
    {
        $this->acquired[] = $tenantId->value() . ':' . $operation->value;
    }

    /**
     * @return list<string>
     */
    public function acquired(): array
    {
        return $this->acquired;
    }
}
