<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

final class InMemoryCustomerRegistrationRepositoryStorageTest extends TestCase
{
    private InMemoryCustomerRegistrationRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCustomerRegistrationRepository();
    }

    public function testASavedRegistrationIsReadBackAsACopy(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');
        $this->repository->save($registration);

        $read = $this->repository->get(RegistrationId::fromString('reg-1'));

        static::assertNotSame($registration, $read);
        static::assertSame('ref-1', $read->externalReference()->value());
        static::assertSame(RegistrationState::Pending, $read->status()->state());
        static::assertNotSame($read, $this->repository->get(RegistrationId::fromString('reg-1')));
    }

    public function testDomainEventsAreNotPersistedNorConsumedFromTheCaller(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');
        $this->repository->save($registration);

        static::assertSame([], $this->repository->get(RegistrationId::fromString('reg-1'))->releaseEvents());
        static::assertCount(1, $registration->releaseEvents());
    }

    public function testAChangeIsOnlyVisibleOnceSaved(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');
        $this->repository->save($registration);
        $registration->startSubmission(CustomerFixtures::now());

        static::assertSame(RegistrationState::Pending, $this->repository->get($registration->id())->status()->state());

        $this->repository->save($registration);

        static::assertSame(RegistrationState::Submitting, $this->repository->get($registration->id())->status()->state());
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
}
