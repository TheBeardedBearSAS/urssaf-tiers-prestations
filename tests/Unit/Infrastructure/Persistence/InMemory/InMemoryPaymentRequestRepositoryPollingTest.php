<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory;

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;

/**
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class InMemoryPaymentRequestRepositoryPollingTest extends TestCase
{
    private InMemoryPaymentRequestRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPaymentRequestRepository();
    }

    /**
     * @param list<PaymentRequest> $requests
     *
     * @return list<string>
     */
    private static function ids(array $requests): array
    {
        return array_map(static fn(PaymentRequest $r): string => $r->id()->value(), $requests);
    }

    private static function now(): Instant
    {
        return Instant::fromIso8601('2022-04-01T00:00:00Z');
    }

    private static function maxAge(): PollingWindow
    {
        return new PollingWindow(new DateInterval('P30D'));
    }

    public function testPollableRequestsAreTheLeastRecentlyPolledFirstNeverPolledFirst(): void
    {
        $this->repository->save(PaymentRequestBuilder::polledAt(PaymentRequestBuilder::submitted('pr-recent', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', 'u-1'), '2022-03-30T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::polledAt(PaymentRequestBuilder::submitted('pr-old', 'tenant-1', 'INV-2', '2022-03-02T00:00:00Z', 'u-2'), '2022-03-10T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-never', 'tenant-1', 'INV-3', '2022-03-03T00:00:00Z', 'u-3'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-never-first', 'tenant-1', 'INV-4', '2022-03-01T12:00:00Z', 'u-4'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-other-tenant', 'tenant-2', 'INV-5', '2022-03-01T00:00:00Z', 'u-5'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-pending', 'tenant-1', 'INV-6', '2022-03-01T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        $pollable = $this->repository->findPollable($tenant, self::now(), self::maxAge(), 10);

        static::assertSame(['pr-never-first', 'pr-never', 'pr-old', 'pr-recent'], self::ids($pollable));
        static::assertSame(
            ['pr-never-first', 'pr-never'],
            self::ids($this->repository->findPollable($tenant, self::now(), self::maxAge(), 2)),
        );
    }

    public function testPollableRequestsPolledAtTheSameInstantKeepTheCreationOrder(): void
    {
        $this->repository->save(PaymentRequestBuilder::polledAt(PaymentRequestBuilder::submitted('pr-late', 'tenant-1', 'INV-1', '2022-03-05T00:00:00Z', 'u-1'), '2022-03-30T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::polledAt(PaymentRequestBuilder::submitted('pr-early', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z', 'u-2'), '2022-03-30T00:00:00Z'));

        static::assertSame(
            ['pr-early', 'pr-late'],
            self::ids($this->repository->findPollable(TenantId::fromString('tenant-1'), self::now(), self::maxAge(), 10)),
        );
    }

    public function testTenantsWithPollableAreDistinctAndFollowTheCreationOrder(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitted('pr-1', 'tenant-b', 'INV-1', '2022-03-05T00:00:00Z', 'u-1'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-2', 'tenant-a', 'INV-2', '2022-03-02T00:00:00Z', 'u-2'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-3', 'tenant-b', 'INV-3', '2022-03-03T00:00:00Z', 'u-3'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-4', 'tenant-c', 'INV-4', '2022-03-01T00:00:00Z'));

        $tenants = array_map(
            static fn(TenantId $t): string => $t->value(),
            $this->repository->tenantsWithPollable(self::now(), self::maxAge()),
        );

        static::assertSame(['tenant-a', 'tenant-b'], $tenants);
    }

    public function testRequestsSubmittedForLongerThanTheMaximumPollingAgeAreNotPollable(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitted('pr-old', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', 'u-1'));
        $window = new PollingWindow(new DateInterval('P30D'), new DateInterval('P30D'), new DateInterval('P31D'));

        static::assertSame([], $this->repository->findPollable(TenantId::fromString('tenant-1'), self::now(), $window, 10));
        static::assertSame([], $this->repository->tenantsWithPollable(self::now(), $window));
    }

    public function testNoTenantWhenNothingIsPollable(): void
    {
        static::assertSame([], $this->repository->tenantsWithPollable(self::now(), self::maxAge()));
    }

    public function testStaleSubmissionsAreThoseStartedAtOrBeforeTheThresholdOldestSubmissionFirst(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitting('pr-b', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', '2022-03-02T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-a', 'tenant-2', 'INV-2', '2022-03-05T00:00:00Z', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-edge', 'tenant-1', 'INV-3', '2022-03-04T00:00:00Z', '2022-03-03T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-fresh', 'tenant-1', 'INV-4', '2022-03-01T00:00:00Z', '2022-03-03T00:00:01Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-pending', 'tenant-1', 'INV-5', '2022-03-01T00:00:00Z'));
        $threshold = Instant::fromIso8601('2022-03-03T00:00:00Z');

        $stale = $this->repository->findStaleSubmissions($threshold, 10);

        static::assertSame(['pr-a', 'pr-b', 'pr-edge'], self::ids($stale));
        static::assertSame(['pr-a'], self::ids($this->repository->findStaleSubmissions($threshold, 1)));
    }

    public function testStaleSubmissionsStartedAtTheSameInstantKeepTheCreationOrder(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitting('pr-late', 'tenant-1', 'INV-1', '2022-03-02T00:00:00Z', '2022-03-02T12:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-early', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z', '2022-03-02T12:00:00Z'));

        static::assertSame(
            ['pr-early', 'pr-late'],
            self::ids($this->repository->findStaleSubmissions(Instant::fromIso8601('2022-03-03T00:00:00Z'), 10)),
        );
    }
}
