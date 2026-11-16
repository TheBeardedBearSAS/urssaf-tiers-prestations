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
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;

final class InMemoryCustomerRegistrationRepositoryUniquenessTest extends TestCase
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

    public function testTheSameExternalReferenceOfAnotherRegistrationOfTheTenantIsRefused(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));

        try {
            $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('customer_registration.duplicate_external_reference', $exception->violationCode());
            static::assertSame(['external_reference' => 'ref-1'], $exception->params());
        }
        $this->expectException(RecordNotFound::class);
        $this->repository->get(RegistrationId::fromString('reg-2'));
    }

    public function testTheSameExternalReferenceIsAllowedForAnotherTenantAndForTheSameRegistration(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-2', 'ref-1', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));

        static::assertSame('tenant-2', $this->repository->get(RegistrationId::fromString('reg-2'))->tenantId()->value());
    }

    public function testTheSameUrssafCustomerIdOfAnotherRegistrationOfTheTenantIsRefused(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-1', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));

        try {
            $this->repository->save(RegistrationBuilder::awaiting('reg-2', 'tenant-1', 'ref-2', self::URSSAF_A, '2026-11-02T00:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('customer_registration.duplicate_urssaf_customer_id', $exception->violationCode());
            static::assertSame(['urssaf_customer_id' => self::URSSAF_A], $exception->params());
        }
    }

    public function testTheSameUrssafCustomerIdIsAllowedForAnotherTenantAndAnotherId(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-1', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-2', 'tenant-2', 'ref-2', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-3', 'tenant-1', 'ref-3', self::URSSAF_B, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-1', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-03T00:00:00Z'));

        static::assertCount(1, $this->repository->findPairingToFollow(TenantId::fromString('tenant-2'), 10));
    }

    public function testRegistrationsWithoutUrssafIdDoNotCollide(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-1', 'ref-2', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-3', 'tenant-1', 'ref-3', self::URSSAF_A, '2026-11-02T00:00:00Z'));

        static::assertSame(['reg-3'], self::ids($this->repository->findPairingToFollow(TenantId::fromString('tenant-1'), 10)));
    }

    public function testAConflictIsFoundAfterRegistrationsOfOtherTenants(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-0', 'tenant-2', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-1', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));

        $this->expectException(InvariantViolation::class);

        $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
    }

    public function testAConflictIsFoundAfterTheRegistrationItself(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-1', 'ref-2', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));

        $this->expectException(InvariantViolation::class);

        $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
    }
}
