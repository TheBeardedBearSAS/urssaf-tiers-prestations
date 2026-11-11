<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Contract\CustomerRegistrationRepositoryContract;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\BeforeNextFlush;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\FileDatabase;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\RegistrationBuilder;

/**
 * Contract and behaviour scenarios of one repository: one method per scenario.
 * @mago-expect lint:too-many-methods
 */
final class DoctrineCustomerRegistrationRepositoryTest extends TestCase
{
    use CustomerRegistrationRepositoryContract;

    private EntityManagerInterface $entityManager;

    private ?FileDatabase $file = null;

    protected function createRepository(): CustomerRegistrationRepository
    {
        $this->entityManager = TestDatabase::entityManager();

        return new DoctrineCustomerRegistrationRepository($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }

    private static function awaitingCreatedAt(string $id, string $reference, string $urssafId, string $createdAt): CustomerRegistration
    {
        $registration = RegistrationBuilder::pending($id, 'tenant-1', $reference, $createdAt);
        $registration->startSubmission(CustomerFixtures::at('2026-11-04T00:00:00Z'));
        $registration->markSubmitted(UrssafCustomerId::fromString($urssafId), CustomerFixtures::at('2026-11-05T00:00:00Z'));

        return $registration;
    }

    public function testItReturnsTheManagedInstanceUntilTheEntityManagerIsCleared(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');
        $this->repository->save($registration);
        $id = RegistrationId::fromString('reg-1');

        static::assertSame($registration, $this->repository->get($id));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($id);

        static::assertNotSame($registration, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        $registration->releaseEvents();
        static::assertEquals($registration, $reloaded);
    }

    public function testAClaimDropsTheStaleManagedCopySoThatTheNextReadSeesTheClaimedRow(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');
        $this->repository->save($registration);

        static::assertTrue($this->repository->claimForSubmission($registration->id(), CustomerFixtures::now()));

        static::assertFalse($this->entityManager->contains($registration));
        $claimed = $this->repository->get($registration->id());
        static::assertNotSame($registration, $claimed);
        static::assertSame(RegistrationState::Submitting, $claimed->status()->state());
    }

    public function testAFailedClaimKeepsTheManagedCopy(): void
    {
        $registration = RegistrationBuilder::awaiting('reg-1', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z');
        $this->repository->save($registration);

        static::assertFalse($this->repository->claimForSubmission($registration->id(), CustomerFixtures::now()));

        static::assertTrue($this->entityManager->contains($registration));
    }

    public function testARefusedDuplicateDoesNotCloseTheEntityManager(): void
    {
        $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));

        try {
            $this->repository->save(RegistrationBuilder::pending('reg-2', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation) {
            static::assertTrue($this->entityManager->isOpen());
        }
    }

    public function testEqualUpdateInstantsAreOrderedByCreationThenIdentifierWhateverTheInsertionOrder(): void
    {
        // Insertion order, creation order and identifier order all differ.
        $this->repository->save(self::awaitingCreatedAt('reg-b', 'ref-b', 'bf0be430-908a-11eb-a8b3-0242ac130003', '2026-11-03T00:00:00Z'));
        $this->repository->save(self::awaitingCreatedAt('reg-c', 'ref-c', 'cf0be430-908a-11eb-a8b3-0242ac130003', '2026-11-01T00:00:00Z'));
        $this->repository->save(self::awaitingCreatedAt('reg-a', 'ref-a', 'af0be430-908a-11eb-a8b3-0242ac130003', '2026-11-03T00:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame(['reg-c', 'reg-a', 'reg-b'], self::ids($this->repository->findByState($tenant, RegistrationState::AwaitingPairing, 10)));
        static::assertSame(['reg-c', 'reg-a', 'reg-b'], self::ids($this->repository->findPairingToFollow($tenant, 10)));
        static::assertSame(['tenant-1'], array_map(static fn(TenantId $t): string => $t->value(), $this->repository->tenantsWithPairingToFollow()));
    }

    public function testStaleSubmissionsStartedAtTheSameInstantAreOrderedByCreationThenIdentifierWhateverTheInsertionOrder(): void
    {
        foreach (['reg-b' => '2026-11-03T00:00:00Z', 'reg-c' => '2026-11-01T00:00:00Z', 'reg-a' => '2026-11-03T00:00:00Z'] as $id => $createdAt) {
            $registration = RegistrationBuilder::pending($id, 'tenant-1', 'ref-' . $id, $createdAt);
            $registration->startSubmission(CustomerFixtures::at('2026-11-04T00:00:00Z'));
            $this->repository->save($registration);
        }

        static::assertSame(['reg-c', 'reg-a', 'reg-b'], self::ids($this->repository->findStaleSubmissions(CustomerFixtures::now(), 10)));
    }

    public function testTwoWorkersOnTheSameDatabaseCannotBothClaim(): void
    {
        $this->file = new FileDatabase();
        $first = new DoctrineCustomerRegistrationRepository($this->file->entityManager());
        $second = new DoctrineCustomerRegistrationRepository($this->file->entityManager());
        $first->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        $id = RegistrationId::fromString('reg-1');
        $staleForSecond = $second->get($id);

        $firstWon = $first->claimForSubmission($id, CustomerFixtures::now());
        $secondWon = $second->claimForSubmission($id, CustomerFixtures::now());

        static::assertTrue($firstWon);
        static::assertFalse($secondWon);
        static::assertSame(RegistrationState::Pending, $staleForSecond->status()->state());
        static::assertSame(RegistrationState::Submitting, (new DoctrineCustomerRegistrationRepository($this->file->entityManager()))->get($id)->status()->state());
    }

    public function testAConcurrentDuplicateExternalReferenceIsReportedAsAViolation(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineCustomerRegistrationRepository($entityManager);
        $rival = new DoctrineCustomerRegistrationRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(RegistrationBuilder::pending('reg-rival', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
        });

        try {
            $repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('customer_registration.duplicate_external_reference', $exception->violationCode());
        }
    }

    public function testAConcurrentDuplicateUrssafCustomerIdIsReportedAsAViolation(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineCustomerRegistrationRepository($entityManager);
        $rival = new DoctrineCustomerRegistrationRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(RegistrationBuilder::awaiting('reg-rival', 'tenant-1', 'ref-rival', self::URSSAF_A, '2026-11-02T00:00:00Z'));
        });

        try {
            $repository->save(RegistrationBuilder::awaiting('reg-1', 'tenant-1', 'ref-1', self::URSSAF_A, '2026-11-02T00:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('customer_registration.duplicate_urssaf_customer_id', $exception->violationCode());
        }
    }

    public function testAnUnexplainedConstraintViolationIsRethrownUnchanged(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrineCustomerRegistrationRepository($entityManager);
        $rival = new DoctrineCustomerRegistrationRepository($this->file->entityManager());
        // Same identifier, different tenant and reference: only the primary key collides.
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(RegistrationBuilder::pending('reg-1', 'tenant-2', 'ref-2', '2026-11-10T09:00:00Z'));
        });

        $this->expectException(UniqueConstraintViolationException::class);
        $repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
    }
}
