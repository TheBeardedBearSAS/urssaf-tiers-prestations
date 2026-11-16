<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Contract;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\StateCount;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\PaymentRequestBuilder;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\RegistrationBuilder;

/**
 * Behaviour shared by every WorkflowStatistics adapter.
 *
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 */
trait WorkflowStatisticsContract
{
    private const AT = '2022-03-01T10:00:00Z';

    /**
     * @return array{WorkflowStatistics, CustomerRegistrationRepository, PaymentRequestRepository} the statistics over the two repositories
     */
    abstract protected function createAdapters(): array;

    public function testNothingIsCountedWithoutRecords(): void
    {
        [$statistics] = $this->createAdapters();

        static::assertSame([], $statistics->registrationStates());
        static::assertSame([], $statistics->paymentRequestStates());
    }

    public function testTheRegistrationsAreCountedByTenantAndState(): void
    {
        [$statistics, $registrations] = $this->createAdapters();
        $registrations->save(RegistrationBuilder::pending('r1', 'tenant-1', 'ref-1', self::AT));
        $registrations->save(RegistrationBuilder::pending('r2', 'tenant-1', 'ref-2', self::AT));
        $registrations->save(RegistrationBuilder::submitting('r3', 'tenant-1', 'ref-3', self::AT));
        $registrations->save(RegistrationBuilder::pending('r4', 'tenant-2', 'ref-1', self::AT));

        static::assertSame(
            ['tenant-1/pending=2', 'tenant-1/submitting=1', 'tenant-2/pending=1'],
            self::normalized($statistics->registrationStates()),
        );
    }

    public function testThePaymentRequestsAreCountedByTenantAndState(): void
    {
        [$statistics, , $paymentRequests] = $this->createAdapters();
        $paymentRequests->save(PaymentRequestBuilder::pending('p1', 'tenant-1', 'INV-1', self::AT));
        $paymentRequests->save(PaymentRequestBuilder::submitted('p2', 'tenant-2', 'INV-2', self::AT, 'urssaf-2'));
        $paymentRequests->save(PaymentRequestBuilder::submitted('p3', 'tenant-2', 'INV-3', self::AT, 'urssaf-3'));

        static::assertSame(['tenant-1/pending=1', 'tenant-2/submitted=2'], self::normalized($statistics->paymentRequestStates()));
    }

    /**
     * @param list<StateCount> $counts
     *
     * @return list<string> "tenant/state=count", sorted (the adapters do not guarantee an order)
     */
    private static function normalized(array $counts): array
    {
        $normalized = array_map(static fn(StateCount $count): string => \sprintf('%s/%s=%d', $count->tenantId->value(), $count->state, $count->count), $counts);
        sort($normalized);

        return $normalized;
    }
}
