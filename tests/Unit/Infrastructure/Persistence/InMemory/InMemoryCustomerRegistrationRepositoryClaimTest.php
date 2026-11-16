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
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

final class InMemoryCustomerRegistrationRepositoryClaimTest extends TestCase
{
    private const URSSAF_A = 'af0be430-908a-11eb-a8b3-0242ac130003';

    private InMemoryCustomerRegistrationRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCustomerRegistrationRepository();
    }

    public function testOnlyOneWorkerWinsTheClaim(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $id = RegistrationId::fromString('reg-1');
        $now = CustomerFixtures::now();

        static::assertTrue($this->repository->claimForSubmission($id, $now));
        static::assertFalse($this->repository->claimForSubmission($id, $now));

        $claimed = $this->repository->get($id);
        static::assertSame(RegistrationState::Submitting, $claimed->status()->state());
        static::assertSame($now->toIso8601(), $claimed->status()->updatedAt()->toIso8601());
        static::assertSame([], $claimed->releaseEvents());
    }

    public function testAClaimDoesNotTouchTheEventsPendingOnTheCallerInstance(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');
        $this->repository->save($registration);

        $this->repository->claimForSubmission($registration->id(), CustomerFixtures::now());

        static::assertCount(1, $registration->releaseEvents());
        static::assertSame(RegistrationState::Pending, $registration->status()->state());
    }

    public function testAnUnknownOrNotPendingRegistrationCannotBeClaimed(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-1', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));

        static::assertFalse($this->repository->claimForSubmission(RegistrationId::fromString('reg-1'), CustomerFixtures::now()));
        static::assertFalse($this->repository->claimForSubmission(RegistrationId::fromString('missing'), CustomerFixtures::now()));
        static::assertSame(
            RegistrationState::AwaitingPairing,
            $this->repository->get(RegistrationId::fromString('reg-1'))->status()->state(),
        );
    }
}
