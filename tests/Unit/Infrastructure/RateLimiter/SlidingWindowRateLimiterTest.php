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
use Symfony\Component\Clock\MockClock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\SlidingWindowRateLimiter;

final class SlidingWindowRateLimiterTest extends TestCase
{
    private const START = '2026-01-01T10:00:00+00:00';

    public function testAllowsCallsUpToTheOfficialQuota(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');

        for ($i = 0; $i < Operation::RegisterCustomer->quotaPerMinute(); ++$i) {
            static::assertNull(self::retryAfterOrNull($limiter, $tenant, Operation::RegisterCustomer));
        }
    }

    public function testRejectsTheCallAboveTheQuota(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::RegisterCustomer);

        $this->expectExceptionObject(RateLimitExceeded::forOperation(Operation::RegisterCustomer, 60));

        $limiter->acquire($tenant, Operation::RegisterCustomer);
    }

    public function testRetryDelayIsTheTimeUntilTheOldestCallLeavesTheWindow(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        $limiter->acquire($tenant, Operation::RegisterCustomer);
        $clock->modify('+10 seconds');
        for ($i = 1; $i < Operation::RegisterCustomer->quotaPerMinute(); ++$i) {
            $limiter->acquire($tenant, Operation::RegisterCustomer);
        }

        static::assertSame(50, self::retryAfterOrNull($limiter, $tenant, Operation::RegisterCustomer));
    }

    public function testExceptionNamesTheOperation(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::GetCustomerStatus);

        try {
            $limiter->acquire($tenant, Operation::GetCustomerStatus);
            static::fail('The quota should be exceeded.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(Operation::GetCustomerStatus, $exception->operation());
        }
    }

    public function testCallsLeaveTheWindowExactlySixtySecondsLater(): void
    {
        $clock = new MockClock(self::START);
        $limiter = new SlidingWindowRateLimiter(new SystemClock($clock));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::RegisterCustomer);

        $clock->modify('+59 seconds');
        static::assertSame(1, self::retryAfterOrNull($limiter, $tenant, Operation::RegisterCustomer));

        $clock->modify('+1 second');
        static::assertNull(self::retryAfterOrNull($limiter, $tenant, Operation::RegisterCustomer));
    }

    public function testQuotaIsCountedPerTenantAndPerOperation(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::RegisterCustomer);

        static::assertNull(self::retryAfterOrNull($limiter, TenantId::fromString('tenant-b'), Operation::RegisterCustomer));
        static::assertNull(self::retryAfterOrNull($limiter, $tenant, Operation::GetCustomerStatus));
    }

    public function testEachOperationUsesItsOwnOfficialQuota(): void
    {
        $limiter = new SlidingWindowRateLimiter(new SystemClock(new MockClock(self::START)));
        $tenant = TenantId::fromString('tenant-a');
        self::fillQuota($limiter, $tenant, Operation::SubmitPaymentRequests);

        static::assertSame(60, self::retryAfterOrNull($limiter, $tenant, Operation::SubmitPaymentRequests));
    }

    private static function fillQuota(
        SlidingWindowRateLimiter $limiter,
        TenantId $tenant,
        Operation $operation,
    ): void {
        for ($i = 0; $i < $operation->quotaPerMinute(); ++$i) {
            $limiter->acquire($tenant, $operation);
        }
    }

    /**
     * Returns the retry delay when the call is rejected, null when it is allowed.
     */
    private static function retryAfterOrNull(
        SlidingWindowRateLimiter $limiter,
        TenantId $tenant,
        Operation $operation,
    ): ?int {
        try {
            $limiter->acquire($tenant, $operation);
        } catch (RateLimitExceeded $exception) {
            return $exception->retryAfterSeconds();
        }

        return null;
    }
}
