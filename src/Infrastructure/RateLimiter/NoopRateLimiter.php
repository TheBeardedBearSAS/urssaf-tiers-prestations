<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Rate limiter that never throttles (the remote quota then applies alone, as HTTP 429).
 */
final class NoopRateLimiter implements RateLimiter
{
    public function acquire(TenantId $tenantId, Operation $operation): void {}
}
