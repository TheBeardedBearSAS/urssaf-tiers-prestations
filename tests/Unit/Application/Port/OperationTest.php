<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;

final class OperationTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation, string, int}>
     */
    public static function provideOperations(): iterable
    {
        yield 'register' => [Operation::RegisterCustomer, '010', 100];
        yield 'status' => [Operation::GetCustomerStatus, '020', 300];
        yield 'submit' => [Operation::SubmitPaymentRequests, '050', 150];
        yield 'search' => [Operation::SearchPaymentRequests, '070', 300];
    }

    #[DataProvider('provideOperations')]
    public function testOfficialNumbersAndQuotas(Operation $operation, string $number, int $quota): void
    {
        static::assertSame($number, $operation->methodNumber());
        static::assertSame($quota, $operation->quotaPerMinute());
    }

    public function testRateLimitExceeded(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::SubmitPaymentRequests, 12);

        static::assertSame(Operation::SubmitPaymentRequests, $exception->operation());
        static::assertSame(12, $exception->retryAfterSeconds());
        static::assertSame('Local rate limit reached for URSSAF method 050 (150 calls per minute); retry in 12 second(s).', $exception->getMessage());
    }

    public function testRateLimitExceededClampsNegativeDelays(): void
    {
        $exception = RateLimitExceeded::forOperation(Operation::RegisterCustomer, -3);

        static::assertSame(0, $exception->retryAfterSeconds());
        static::assertStringEndsWith('retry in 0 second(s).', $exception->getMessage());
        static::assertSame(0, RateLimitExceeded::forOperation(Operation::RegisterCustomer, 0)->retryAfterSeconds());
    }
}
