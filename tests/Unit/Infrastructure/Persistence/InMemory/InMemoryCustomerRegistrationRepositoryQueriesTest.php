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
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;

final class InMemoryCustomerRegistrationRepositoryQueriesTest extends TestCase
{
    private const URSSAF_A = 'af0be430-908a-11eb-a8b3-0242ac130003';
    private const URSSAF_B = 'bf0be430-908a-11eb-a8b3-0242ac130003';

    private InMemoryCustomerRegistrationRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCustomerRegistrationRepository();
    }

    /**
     * @param list<CustomerRegistration> $registrations
     *
     * @return list<string>
     */
    private static function ids(array $registrations): array
    {
        return array_map(static fn(CustomerRegistration $r): string => $r->id()->value(), $registrations);
    }

    public function testItFindsByExternalReference(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-2', 'ref-1', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-3', 'tenant-2', 'ref-3', '2026-11-10T09:00:00Z'));
        $tenant = TenantId::fromString('tenant-2');

        $found = $this->repository->findByExternalReference($tenant, ExternalReference::fromString('ref-1'));

        static::assertSame('reg-2', $found?->id()->value());
        static::assertNull($this->repository->findByExternalReference($tenant, ExternalReference::fromString('ref-9')));
        static::assertNull($this->repository->findByExternalReference(TenantId::fromString('tenant-3'), ExternalReference::fromString('ref-1')));
    }

    public function testItFindsByUrssafCustomerId(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-0', 'tenant-1', 'ref-0', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-1', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-2', 'tenant-2', 'ref-2', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        $a = UrssafCustomerId::fromString(self::URSSAF_A);

        static::assertSame('reg-2', $this->repository->findByUrssafCustomerId(TenantId::fromString('tenant-2'), $a)?->id()->value());
        static::assertSame('reg-1', $this->repository->findByUrssafCustomerId(TenantId::fromString('tenant-1'), $a)?->id()->value());
        static::assertNull($this->repository->findByUrssafCustomerId(TenantId::fromString('tenant-1'), UrssafCustomerId::fromString(self::URSSAF_B)));
        static::assertNull($this->repository->findByUrssafCustomerId(TenantId::fromString('tenant-3'), $a));
    }

    public function testAwaitingPairingIsOrderedByLastUpdateOldestFirstAndLimited(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-new', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-05T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-old', 'tenant-1', 'ref-2', self::URSSAF_B, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-other', 'tenant-2', 'ref-3', self::URSSAF_A, '2026-11-01T12:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-pending', 'tenant-1', 'ref-4', '2026-11-01T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame(['reg-old', 'reg-new'], self::ids($this->repository->findPairingToFollow($tenant, 10)));
        static::assertSame(['reg-old'], self::ids($this->repository->findPairingToFollow($tenant, 1)));
    }

    public function testEqualUpdateInstantsKeepTheInsertionOrder(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-b', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-a', 'tenant-1', 'ref-2', self::URSSAF_B, '2026-11-02T00:00:00Z'));

        static::assertSame(
            ['reg-b', 'reg-a'],
            self::ids($this->repository->findPairingToFollow(TenantId::fromString('tenant-1'), 10)),
        );
    }

    public function testItFindsByState(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-1', 'ref-2', '2026-11-09T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-3', 'tenant-2', 'ref-3', '2026-11-08T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-4', 'tenant-1', 'ref-4', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame(['reg-2', 'reg-1'], self::ids($this->repository->findByState($tenant, RegistrationState::Pending, 10)));
        static::assertSame(['reg-2'], self::ids($this->repository->findByState($tenant, RegistrationState::Pending, 1)));
        static::assertSame([], $this->repository->findByState($tenant, RegistrationState::Rejected, 10));
    }

    public function testTenantsWithAwaitingPairingAreDistinctAndOrderedByOldestUpdate(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-1', 'tenant-b', 'ref-1', self::URSSAF_A, '2026-11-05T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-2', 'tenant-a', 'ref-2', self::URSSAF_A, '2026-11-03T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-3', 'tenant-b', 'ref-3', self::URSSAF_B, '2026-11-04T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-4', 'tenant-c', 'ref-4', '2026-11-01T00:00:00Z'));

        $tenants = array_map(static fn(TenantId $t): string => $t->value(), $this->repository->tenantsWithPairingToFollow());

        static::assertSame(['tenant-a', 'tenant-b'], $tenants);
    }

    public function testNoTenantWhenNothingIsAwaiting(): void
    {
        static::assertSame([], $this->repository->tenantsWithPairingToFollow());
    }
}
