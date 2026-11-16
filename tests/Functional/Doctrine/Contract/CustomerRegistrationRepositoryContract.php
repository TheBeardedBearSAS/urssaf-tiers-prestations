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
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Customer;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\RegistrationBuilder;

/**
 * Behaviour every {@see CustomerRegistrationRepository} must have, independently of the storage. Identity-related
 * behaviour (copies versus managed instances) is deliberately not part of the contract.
 *
 * Contract and behaviour scenarios of one repository: one method per scenario.
 * @phpstan-require-extends TestCase
 * @require-extends TestCase
 * @mago-expect lint:too-many-methods
 */
trait CustomerRegistrationRepositoryContract
{
    protected const URSSAF_A = 'af0be430-908a-11eb-a8b3-0242ac130003';

    protected const URSSAF_B = 'bf0be430-908a-11eb-a8b3-0242ac130003';

    protected const URSSAF_C = 'cf0be430-908a-11eb-a8b3-0242ac130003';

    protected const URSSAF_D = 'df0be430-908a-11eb-a8b3-0242ac130003';

    protected CustomerRegistrationRepository $repository;

    abstract protected function createRepository(): CustomerRegistrationRepository;

    /**
     * @param list<CustomerRegistration> $registrations
     *
     * @return list<string>
     */
    protected static function ids(array $registrations): array
    {
        return array_map(static fn(CustomerRegistration $r): string => $r->id()->value(), $registrations);
    }

    protected function setUp(): void
    {
        $this->repository = $this->createRepository();
    }

    public function testASavedRegistrationIsReadBack(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));

        $read = $this->repository->get(RegistrationId::fromString('reg-1'));

        static::assertSame('ref-1', $read->externalReference()->value());
        static::assertSame('tenant-1', $read->tenantId()->value());
        static::assertSame(RegistrationState::Pending, $read->status()->state());
    }

    public function testAnUnknownRegistrationIsNotFound(): void
    {
        try {
            $this->repository->get(RegistrationId::fromString('missing'));
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('customer_registration.not_found', $exception->violationCode());
            static::assertSame('missing', $exception->identifier());
        }
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

    public function testEqualUpdateInstantsAreOrderedByCreationThenIdentifier(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-a', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::awaiting('reg-b', 'tenant-1', 'ref-2', self::URSSAF_B, '2026-11-02T00:00:00Z'));

        static::assertSame(
            ['reg-a', 'reg-b'],
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

    public function testDomainEventsAreNotPersistedNorConsumedFromTheCaller(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');

        $this->repository->save($registration);

        static::assertCount(1, $registration->releaseEvents());
    }

    public function testASavedChangeIsReadBack(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');
        $this->repository->save($registration);

        $registration->startSubmission(CustomerFixtures::now());
        $this->repository->save($registration);

        static::assertSame(RegistrationState::Submitting, $this->repository->get($registration->id())->status()->state());
    }

    public function testThePairingToFollowCoversAwaitingBlockedAndMandateExpiredOldestUpdateFirst(): void
    {
        $this->repository->save(RegistrationBuilder::awaiting('reg-awaiting', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-05T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::paired('reg-blocked', 'tenant-1', self::URSSAF_B, PairingStatus::CustomerBlocked, '2026-11-03T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::paired('reg-expired', 'tenant-1', self::URSSAF_C, PairingStatus::MandateExpired, '2026-11-04T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::paired('reg-registered', 'tenant-1', self::URSSAF_D, PairingStatus::Validated, '2026-11-02T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::paired('reg-other', 'tenant-2', self::URSSAF_A, PairingStatus::CustomerBlocked, '2026-11-01T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame(['reg-blocked', 'reg-expired', 'reg-awaiting'], self::ids($this->repository->findPairingToFollow($tenant, 10)));
        static::assertSame(['reg-blocked', 'reg-expired'], self::ids($this->repository->findPairingToFollow($tenant, 2)));
    }

    public function testTenantsWithOnlyBlockedOrExpiredPairingsAreFollowedButNotRegisteredOnes(): void
    {
        $this->repository->save(RegistrationBuilder::paired('reg-1', 'tenant-blocked', self::URSSAF_A, PairingStatus::CustomerBlocked, '2026-11-04T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::paired('reg-2', 'tenant-expired', self::URSSAF_A, PairingStatus::MandateExpired, '2026-11-03T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::paired('reg-3', 'tenant-registered', self::URSSAF_A, PairingStatus::Validated, '2026-11-02T00:00:00Z'));

        $tenants = array_map(static fn(TenantId $t): string => $t->value(), $this->repository->tenantsWithPairingToFollow());

        static::assertSame(['tenant-expired', 'tenant-blocked'], $tenants);
    }

    public function testStaleSubmissionsOfEveryTenantStartedAtOrBeforeTheLimitAreFoundOldestAttemptFirst(): void
    {
        $this->repository->save(RegistrationBuilder::submitting('reg-limit', 'tenant-1', 'ref-1', '2026-11-10T10:00:00Z'));
        $this->repository->save(RegistrationBuilder::submitting('reg-oldest', 'tenant-2', 'ref-2', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::submitting('reg-recent', 'tenant-1', 'ref-3', '2026-11-10T10:00:01Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-pending', 'tenant-1', 'ref-4', '2026-11-01T00:00:00Z'));
        $this->repository->save(RegistrationBuilder::rejected('reg-rejected', 'tenant-1', 'ref-5', '2026-11-01T00:00:00Z'));
        $olderThan = CustomerFixtures::at('2026-11-10T10:00:00Z');

        static::assertSame(['reg-oldest', 'reg-limit'], self::ids($this->repository->findStaleSubmissions($olderThan, 10)));
        static::assertSame(['reg-oldest'], self::ids($this->repository->findStaleSubmissions($olderThan, 1)));
    }

    public function testAClaimCountsTheAttemptAndRecordsItsStart(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $id = RegistrationId::fromString('reg-1');
        $first = CustomerFixtures::at('2026-11-10T10:00:00Z');
        $second = CustomerFixtures::at('2026-11-10T11:00:00Z');

        $this->repository->claimForSubmission($id, $first);
        $failed = $this->repository->get($id);
        $failed->markFailed([CustomerFixtures::cause()], $first);
        $failed->retry($first);
        $this->repository->save($failed);
        $this->repository->claimForSubmission($id, $second);

        $claimed = $this->repository->get($id);
        static::assertSame(2, $claimed->status()->attempts());
        static::assertSame($second->toIso8601(), $claimed->status()->lastAttemptAt()?->toIso8601());
        static::assertSame([CustomerFixtures::cause()->code()], array_map(static fn(RejectionCause $cause): string => $cause->code(), $claimed->status()->lastErrors()));
    }

    public function testADeletedRegistrationIsGoneAndItsExternalReferenceIsFree(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-1', 'ref-2', '2026-11-10T09:00:00Z'));

        $this->repository->delete(RegistrationId::fromString('reg-1'));
        $this->repository->delete(RegistrationId::fromString('missing'));
        $this->repository->save(RegistrationBuilder::pending('reg-3', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));

        static::assertSame(['reg-2', 'reg-3'], self::ids($this->repository->findByState(TenantId::fromString('tenant-1'), RegistrationState::Pending, 10)));
        $this->expectException(RecordNotFound::class);
        $this->repository->get(RegistrationId::fromString('reg-1'));
    }

    public function testAnAnonymisedRegistrationIsReadBackWithTheAnonymisedCustomer(): void
    {
        $registration = RegistrationBuilder::rejected('reg-1', 'tenant-1', 'ref-1', '2026-11-02T00:00:00Z');
        $this->repository->save($registration);

        $registration->anonymise(CustomerFixtures::now());
        $this->repository->save($registration);

        $read = $this->repository->get(RegistrationId::fromString('reg-1'));
        static::assertTrue($read->customer()->equals(Customer::anonymised()));
        static::assertSame(CustomerFixtures::NOW, $read->status()->updatedAt()->toIso8601());
    }
}
