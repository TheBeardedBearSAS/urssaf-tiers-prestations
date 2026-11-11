<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrinePaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\HostNote;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\Samples;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\RegistrationBuilder;

final class DoctrineTransactionManagerTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineCustomerRegistrationRepository $repository;

    private DoctrineTransactionManager $transactions;

    protected function setUp(): void
    {
        $this->entityManager = TestDatabase::entityManager();
        $this->repository = new DoctrineCustomerRegistrationRepository($this->entityManager);
        $this->transactions = new DoctrineTransactionManager($this->entityManager);
    }

    public function testItCommitsAndReturnsTheResultOfTheOperation(): void
    {
        $connection = $this->entityManager->getConnection();

        $result = $this->transactions->transactional(function () use ($connection): string {
            $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));

            return 'done:' . ($connection->isTransactionActive() ? 'in' : 'out');
        });

        static::assertSame('done:in', $result);
        static::assertFalse($connection->isTransactionActive());
        $this->entityManager->clear();
        static::assertSame('reg-1', $this->repository->get(RegistrationId::fromString('reg-1'))->id()->value());
    }

    public function testItRethrowsTheSameThrowable(): void
    {
        $failure = new LogicException('boom');

        $this->expectExceptionObject($failure);
        $this->transactions->transactional(static function () use ($failure): never {
            throw $failure;
        });
    }

    public function testItRollsBackAndClearsTheEntityManagerWhenTheOperationFails(): void
    {
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');

        try {
            $this->transactions->transactional(function () use ($registration): never {
                $this->repository->save($registration);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        static::assertTrue($this->entityManager->isOpen());
        static::assertFalse($this->entityManager->contains($registration));
        $this->expectException(RecordNotFound::class);
        $this->repository->get(RegistrationId::fromString('reg-1'));
    }

    public function testItKeepsTheEntitiesOfTheHostApplicationManagedWhenTheOperationFails(): void
    {
        $entityManager = TestDatabase::sharedEntityManager();
        $repository = new DoctrineCustomerRegistrationRepository($entityManager);
        $transactions = new DoctrineTransactionManager($entityManager);
        $note = new HostNote('flushed before');
        $entityManager->persist($note);
        $entityManager->flush();
        $registration = RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z');
        $note->rewrite('changed, not flushed yet');

        try {
            $transactions->transactional(static function () use ($repository, $registration): never {
                $repository->save($registration);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertFalse($entityManager->contains($registration));
        static::assertTrue($entityManager->contains($note));
        static::assertSame($note, $entityManager->find(HostNote::class, $note->id()));
        static::assertSame('changed, not flushed yet', $note->text());
    }

    public function testItDetachesTheProgressRowsAndPaymentRequestsToo(): void
    {
        $paymentRequest = Samples::paymentRequestCase('official');
        $repository = new DoctrinePaymentRequestRepository($this->entityManager);

        try {
            $this->transactions->transactional(static function () use ($repository, $paymentRequest): never {
                $repository->save($paymentRequest);

                throw new LogicException('boom');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        static::assertFalse($this->entityManager->contains($paymentRequest));
        $managed = 0;
        foreach ($this->entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
            $managed += \count($entities);
        }
        static::assertSame(0, $managed);
    }

    public function testNestedOperationsJoinTheOutermostTransaction(): void
    {
        try {
            $this->transactions->transactional(function (): never {
                $this->transactions->transactional(function (): void {
                    $this->repository->save(RegistrationBuilder::pending('reg-1', 'tenant-1', 'ref-1', '2026-11-10T09:00:00Z'));
                });

                throw new LogicException('outer failure');
            });
        } catch (LogicException) {
            static::addToAssertionCount(1);
        }

        $this->expectException(RecordNotFound::class);
        $this->repository->get(RegistrationId::fromString('reg-1'));
    }
}
