<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\RateLimiter;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\NoopRateLimiter;

final class NoopRateLimiterTest extends TestCase
{
    public function testNeverThrottles(): void
    {
        $limiter = new NoopRateLimiter();
        $tenant = TenantId::fromString('tenant-a');

        for ($i = 0; $i < 500; ++$i) {
            $limiter->acquire($tenant, Operation::RegisterCustomer);
        }

        $this->expectNotToPerformAssertions();
    }
}
