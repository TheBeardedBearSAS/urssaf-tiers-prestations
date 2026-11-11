<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrinePaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Contract\PaymentRequestRepositoryContract;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\BeforeNextFlush;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\FileDatabase;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory\PaymentRequestBuilder;

/**
 * Contract and behaviour scenarios of one repository: one method per scenario.
 * @mago-expect lint:too-many-methods
 */
final class DoctrinePaymentRequestRepositoryTest extends TestCase
{
    use PaymentRequestRepositoryContract;

    private EntityManagerInterface $entityManager;

    private ?FileDatabase $file = null;

    protected function createRepository(): PaymentRequestRepository
    {
        $this->entityManager = TestDatabase::entityManager();

        return new DoctrinePaymentRequestRepository($this->entityManager);
    }

    protected function tearDown(): void
    {
        $this->file?->delete();
    }

    public function testItReturnsTheManagedInstanceUntilTheEntityManagerIsCleared(): void
    {
        $request = PaymentRequestBuilder::submitted('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', 'urssaf-1');
        $this->repository->save($request);
        $id = LocalPaymentRequestId::fromString('pr-1');

        static::assertSame($request, $this->repository->get($id));

        $this->entityManager->clear();
        $reloaded = $this->repository->get($id);

        static::assertNotSame($request, $reloaded);
        static::assertSame([], $reloaded->releaseEvents());
        $request->releaseEvents();
        static::assertEquals($request, $reloaded);
    }

    public function testTheSameExternalReferenceOfAnotherRequestOfTheTenantIsRefused(): void
    {
        $first = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');
        $this->repository->save($first);
        // PaymentRequestBuilder derives the external reference from the identifier: same reference, other identifier.
        $other = $this->withExternalReference('pr-1', 'pr-2', 'INV-2');

        try {
            $this->repository->save($other);
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('payment_request.duplicate_external_reference', $exception->violationCode());
            static::assertSame(['external_reference' => 'ext-pr-1'], $exception->params());
        }
    }

    public function testAClaimDropsTheStaleManagedCopySoThatTheNextReadSeesTheClaimedRow(): void
    {
        $request = PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z');
        $this->repository->save($request);

        static::assertTrue($this->repository->claimForSubmission($request->id(), Instant::fromIso8601('2022-03-02T10:00:00Z')));

        static::assertFalse($this->entityManager->contains($request));
        $claimed = $this->repository->get($request->id());
        static::assertNotSame($request, $claimed);
        static::assertSame(SubmissionState::Submitting, $claimed->state());
    }

    public function testAFailedClaimKeepsTheManagedCopy(): void
    {
        $request = PaymentRequestBuilder::submitting('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', '2022-03-02T00:00:00Z');
        $this->repository->save($request);

        static::assertFalse($this->repository->claimForSubmission($request->id(), Instant::fromIso8601('2022-03-03T00:00:00Z')));

        static::assertTrue($this->entityManager->contains($request));
    }

    public function testAClaimedRequestIsNotPollableNorPendingAnyMore(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->claimForSubmission(LocalPaymentRequestId::fromString('pr-1'), Instant::fromIso8601('2022-03-02T10:00:00Z'));
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame([], $this->repository->findByState($tenant, SubmissionState::Pending, 10));
        static::assertSame(['pr-1'], self::ids($this->repository->findByState($tenant, SubmissionState::Submitting, 10)));
        static::assertSame(
            ['pr-1'],
            self::ids($this->repository->findStaleSubmissions(Instant::fromIso8601('2022-03-02T10:00:00Z'), 10)),
        );
    }

    public function testTheProgressColumnsFollowEverySave(): void
    {
        $request = PaymentRequestBuilder::submitted('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z', 'urssaf-1');
        $this->repository->save($request);
        $tenant = TenantId::fromString('tenant-1');
        static::assertSame(['pr-1'], self::ids($this->repository->findByUrssafIds($tenant, [PaymentRequestId::fromString('urssaf-1')])));

        PaymentRequestBuilder::polledAt($request, '2022-03-30T00:00:00Z');
        $request->applyUrssafStatus('40', null, null, Instant::fromIso8601('2022-03-31T00:00:00Z'));
        $this->repository->save($request);

        static::assertSame([], $this->repository->findPollable($tenant, self::now(), self::window(), 10));
        static::assertSame(['pr-1'], self::ids($this->repository->findByUrssafIds($tenant, [PaymentRequestId::fromString('urssaf-1')])));
    }

    public function testARefusedDuplicateDoesNotCloseTheEntityManager(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));

        try {
            $this->repository->save(PaymentRequestBuilder::pending('pr-2', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation) {
            static::assertTrue($this->entityManager->isOpen());
        }
    }

    public function testTheProgressStateFollowsTheClaimOfThatRequestOnly(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-2', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::submitted('pr-3', 'tenant-1', 'INV-3', '2022-03-01T00:00:00Z', 'urssaf-3'));
        $now = Instant::fromIso8601('2022-03-02T10:00:00Z');

        $this->repository->claimForSubmission(LocalPaymentRequestId::fromString('pr-1'), $now);
        $this->repository->claimForSubmission(LocalPaymentRequestId::fromString('pr-3'), $now);

        static::assertSame(
            ['pr-1' => 'submitting', 'pr-2' => 'pending', 'pr-3' => 'submitted'],
            $this->entityManager->getConnection()->fetchAllKeyValue('SELECT id, state FROM urssaf_tps_payment_request_progress ORDER BY id'),
        );
    }

    public function testDeletingARequestAlsoRemovesItsProgressRow(): void
    {
        $this->repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $this->repository->save(PaymentRequestBuilder::pending('pr-2', 'tenant-1', 'INV-2', '2022-03-01T00:00:00Z'));

        $this->repository->delete(LocalPaymentRequestId::fromString('pr-1'));

        static::assertSame(
            ['pr-2' => 'pending'],
            $this->entityManager->getConnection()->fetchAllKeyValue('SELECT id, state FROM urssaf_tps_payment_request_progress ORDER BY id'),
        );
    }

    public function testTwoWorkersOnTheSameDatabaseCannotBothClaim(): void
    {
        $this->file = new FileDatabase();
        $first = new DoctrinePaymentRequestRepository($this->file->entityManager());
        $second = new DoctrinePaymentRequestRepository($this->file->entityManager());
        $first->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        $id = LocalPaymentRequestId::fromString('pr-1');
        $staleForSecond = $second->get($id);
        $now = Instant::fromIso8601('2022-03-02T10:00:00Z');

        $firstWon = $first->claimForSubmission($id, $now);
        $secondWon = $second->claimForSubmission($id, $now);

        static::assertTrue($firstWon);
        static::assertFalse($secondWon);
        static::assertSame(SubmissionState::Pending, $staleForSecond->state());
        static::assertSame(
            SubmissionState::Submitting,
            (new DoctrinePaymentRequestRepository($this->file->entityManager()))->get($id)->state(),
        );
    }

    public function testAConcurrentDuplicateInvoiceNumberIsReportedAsAViolation(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrinePaymentRequestRepository($entityManager);
        $rival = new DoctrinePaymentRequestRepository($this->file->entityManager());
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(PaymentRequestBuilder::pending('pr-rival', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
        });

        try {
            $repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
            static::fail('Expected a violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('payment_request.duplicate_invoice_number', $exception->violationCode());
        }
    }

    public function testAnUnexplainedConstraintViolationIsRethrownUnchanged(): void
    {
        $this->file = new FileDatabase();
        $entityManager = $this->file->entityManager();
        $repository = new DoctrinePaymentRequestRepository($entityManager);
        $rival = new DoctrinePaymentRequestRepository($this->file->entityManager());
        // Same identifier, other tenant, invoice number and reference: only the primary key collides.
        BeforeNextFlush::run($entityManager, static function () use ($rival): void {
            $rival->save(PaymentRequestBuilder::pending('pr-1', 'tenant-2', 'INV-2', '2022-03-01T00:00:00Z'));
        });

        $this->expectException(UniqueConstraintViolationException::class);
        $repository->save(PaymentRequestBuilder::pending('pr-1', 'tenant-1', 'INV-1', '2022-03-01T00:00:00Z'));
    }

    private function withExternalReference(string $referenceOf, string $id, string $invoiceNumber): PaymentRequest
    {
        $base = PaymentRequestBuilder::pending($id, 'tenant-1', $invoiceNumber, '2022-03-01T00:00:00Z');

        return PaymentRequest::create(
            $base->id(),
            $base->tenantId(),
            ExternalReference::fromString('ext-' . $referenceOf),
            $base->invoice(),
            Instant::fromIso8601('2022-03-01T00:00:00Z'),
        );
    }
}
