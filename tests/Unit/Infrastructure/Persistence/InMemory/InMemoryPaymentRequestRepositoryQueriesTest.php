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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;

final class InMemoryPaymentRequestRepositoryQueriesTest extends TestCase
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

    public function testItFindsByUrssafIdsIgnoringUnknownOnesAndOtherTenants(): void
    {
        $this->repository->save(PaymentRequestBuilder::submitted('pr-late', 'tenant-1', 'INV-1', '2022-03-05T00:00:00Z', 'urssaf-1'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-early', 'tenant-1', 'INV-2', '2022-03-02T00:00:00Z', 'urssaf-2'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-other', 'tenant-2', 'INV-3', '2022-03-01T00:00:00Z', 'urssaf-1'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-unwanted', 'tenant-1', 'INV-4', '2022-03-01T00:00:00Z', 'urssaf-4'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-pending', 'tenant-1', 'INV-5', '2022-03-01T00:00:00Z'));

        $found = $this->repository->findByUrssafIds(TenantId::fromString('tenant-1'), [
            PaymentRequestId::fromString('urssaf-1'),
            PaymentRequestId::fromString('urssaf-2'),
            PaymentRequestId::fromString('unknown'),
        ]);

        static::assertSame(['pr-early', 'pr-late'], self::ids($found));
        static::assertSame([], $this->repository->findByUrssafIds(TenantId::fromString('tenant-1'), []));
    }

    public function testItFindsByStateOrderedByCreationAndLimited(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-new', 'tenant-1', 'INV-1', '2022-03-05T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-old', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-other', 'tenant-2', 'INV-3', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitting('pr-busy', 'tenant-1', 'INV-4', '2022-03-01T00:00:00Z', '2022-03-02T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame(['pr-old', 'pr-new'], self::ids($this->repository->findByState($tenant, SubmissionState::Pending, 10)));
        static::assertSame(['pr-old'], self::ids($this->repository->findByState($tenant, SubmissionState::Pending, 1)));
        static::assertSame(['pr-busy'], self::ids($this->repository->findByState($tenant, SubmissionState::Submitting, 10)));
        static::assertSame([], $this->repository->findByState($tenant, SubmissionState::Error, 10));
    }

    public function testRequestsCreatedAtTheSameInstantKeepTheInsertionOrder(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-b', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-a', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z'));

        static::assertSame(
            ['pr-b', 'pr-a'],
            self::ids($this->repository->findByState(TenantId::fromString('tenant-1'), SubmissionState::Pending, 10)),
        );
    }
}
