<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Entity\PaymentRequestProgress;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InvoiceNumberType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\LocalPaymentRequestIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Doctrine ORM implementation of {@see PaymentRequestRepository}, with the semantics of the in-memory one: lists are
 * ordered by creation (oldest first, then id) unless the port says otherwise, uniqueness violations are reported as
 * {@see InvariantViolation}.
 *
 * Unit of work: {@see self::save()} persists and flushes immediately (a flush writes every dirty managed entity) in one
 * transaction, so inside a {@see DoctrineTransactionManager} transaction the save is durable when that transaction
 * commits. An aggregate that is not managed (created by
 * the caller, or detached by clear()) replaces the stored row of the same identity, like a save in the in-memory
 * repository. Reads return the managed instance, so unlike the
 * in-memory repository a change is visible to every holder of the instance before it is saved.
 *
 * Polling: the aggregate keeps its URSSAF tracking as a JSON document; save() also writes the filterable columns
 * (URSSAF id, raw status, status instant, poll instant) mapped by {@see PaymentRequestProgress}. The "settled for less
 * than the maximum age" rule is evaluated as "status instant > now - maximum age"; it differs from the domain rule
 * (status instant + maximum age > now) only for month-based intervals across month ends.
 *
 * Claiming: {@see self::claimForSubmission()} is one conditional UPDATE, never an object round-trip.
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness, claim and progress helpers.
 * @mago-expect lint:too-many-methods
 */
final readonly class DoctrinePaymentRequestRepository implements PaymentRequestRepository
{
    private const ENTITY = PaymentRequest::class;

    private const PROGRESS = PaymentRequestProgress::class;

    private const FROM = ' FROM ' . self::ENTITY . ' p JOIN ' . self::PROGRESS . ' g WITH g.id = p.id';

    private const POLLABLE = ' p.state = :submitted AND p.timeline.submittedAt > :submittedSince'
        . ' AND (g.urssafStatus IS NULL OR (g.urssafStatus NOT IN (:finals)'
        . ' AND (g.urssafStatus NOT IN (:settled) OR g.lastStatusChangeAt > :settledSince)'
        . ' AND (g.urssafStatus IN (:known) OR g.lastStatusChangeAt > :unknownSince)))';

    private const BY_CREATION = ' ORDER BY p.timeline.createdAt ASC, p.id ASC';

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws InvariantViolation "payment_request.duplicate_invoice_number" or
     *                            "payment_request.duplicate_external_reference"
     */
    public function save(PaymentRequest $paymentRequest): void
    {
        $this->assertUnique($paymentRequest);
        try {
            $this->store($paymentRequest);
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent writer won the race between the check above and the flush.
            $this->assertUnique($paymentRequest);

            throw $exception;
        }
    }

    public function get(LocalPaymentRequestId $id): PaymentRequest
    {
        return $this->entityManager->find(self::ENTITY, $id) ?? throw RecordNotFound::withIdentifier(
            'payment_request',
            $id->value(),
        );
    }

    public function findByInvoiceNumber(TenantId $tenantId, InvoiceNumber $invoiceNumber): ?PaymentRequest
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery('SELECT p FROM ' . self::ENTITY . ' p WHERE p.tenantId = :tenant AND p.invoice.number = :number')
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('number', $invoiceNumber, InvoiceNumberType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function findByUrssafIds(TenantId $tenantId, array $paymentRequestIds): array
    {
        $ids = [];
        foreach ($paymentRequestIds as $paymentRequestId) {
            $ids[] = $paymentRequestId->value();
        }

        return Hydration::all(
            $this->entityManager
                ->createQuery('SELECT p' . self::FROM . ' WHERE p.tenantId = :tenant AND g.paymentRequestId IN (:ids)' . self::BY_CREATION)
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('ids', $ids, ArrayParameterType::STRING)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function findPollable(TenantId $tenantId, Instant $now, PollingWindow $window, int $limit): array
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT p, CASE WHEN g.lastPolledAt IS NULL THEN 0 ELSE 1 END AS HIDDEN polledRank' . self::FROM
                . ' WHERE p.tenantId = :tenant AND' . self::POLLABLE
                . ' ORDER BY polledRank ASC, g.lastPolledAt ASC, p.timeline.createdAt ASC, p.id ASC',
            )
            ->setParameter('tenant', $tenantId, TenantIdType::NAME)
            ->setMaxResults($limit);
        foreach (PollableParameters::of($now, $window) as $name => [$value, $type]) {
            $query->setParameter($name, $value, $type);
        }

        return Hydration::all($query->toIterable(), self::ENTITY);
    }

    public function tenantsWithPollable(Instant $now, PollingWindow $window): array
    {
        $query = $this->entityManager->createQuery(
            'SELECT p.tenantId, MIN(p.timeline.createdAt) AS HIDDEN oldestCreation' . self::FROM
            . ' WHERE' . self::POLLABLE . ' GROUP BY p.tenantId ORDER BY oldestCreation ASC, p.tenantId ASC',
        );
        foreach (PollableParameters::of($now, $window) as $name => [$value, $type]) {
            $query->setParameter($name, $value, $type);
        }
        /** @var list<string> $tenants */
        $tenants = $query->getSingleColumnResult();

        return array_map(TenantId::fromString(...), $tenants);
    }

    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT p FROM ' . self::ENTITY . ' p WHERE p.state = :submitting AND p.timeline.submissionStartedAt <= :olderThan'
                    . ' ORDER BY p.timeline.submissionStartedAt ASC, p.timeline.createdAt ASC, p.id ASC',
                )
                ->setParameter('submitting', SubmissionState::Submitting)
                ->setParameter('olderThan', $olderThan, InstantType::NAME)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function findByState(TenantId $tenantId, SubmissionState $state, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery('SELECT p FROM ' . self::ENTITY . ' p WHERE p.tenantId = :tenant AND p.state = :state' . self::BY_CREATION)
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('state', $state)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function delete(LocalPaymentRequestId $id): void
    {
        $this->connection()->transactional(function () use ($id): void {
            $this->discard($id);
            $this->connection()->delete($this->progressTable(), ['id' => $id], PaymentRequestProgress::TYPES);
        });
    }

    public function claimForSubmission(LocalPaymentRequestId $id, Instant $now): bool
    {
        $claimed = $this->connection()->transactional(function () use ($id, $now): bool {
            $won = $this->entityManager
                ->createQuery(
                    'UPDATE ' . self::ENTITY . ' p SET p.state = :submitting, p.attempts = p.attempts + 1, p.timeline.submissionStartedAt = :now,'
                    . ' p.timeline.updatedAt = :now WHERE p.id = :id AND p.state = :pending',
                )
                ->setParameter('submitting', SubmissionState::Submitting)
                ->setParameter('pending', SubmissionState::Pending)
                ->setParameter('now', $now, InstantType::NAME)
                ->setParameter('id', $id, LocalPaymentRequestIdType::NAME)
                ->execute() === 1;
            if ($won) {
                $this->connection()->update(
                    $this->progressTable(),
                    ['state' => SubmissionState::Submitting->value],
                    ['id' => $id],
                    PaymentRequestProgress::TYPES,
                );
            }

            return $won;
        });
        if ($claimed) {
            // The managed copy (if any) is now stale: drop it so that the next get() reads the claimed row.
            $this->detach($id);
        }

        return $claimed;
    }

    private function store(PaymentRequest $paymentRequest): void
    {
        $replaces = !$this->entityManager->contains($paymentRequest) && $this->isStored($paymentRequest->id());
        $this->connection()->transactional(function () use ($paymentRequest, $replaces): void {
            if ($replaces) {
                $this->discard($paymentRequest->id());
            }
            $this->entityManager->persist($paymentRequest);
            $this->entityManager->flush();
            $this->replaceProgress($paymentRequest);
        });
    }

    /**
     * Rewrites the query-side row from the aggregate (delete and insert: no existence check, no driver-specific
     * "affected rows" semantics).
     */
    private function replaceProgress(PaymentRequest $paymentRequest): void
    {
        $this->connection()->delete($this->progressTable(), ['id' => $paymentRequest->id()], PaymentRequestProgress::TYPES);
        $this->connection()->insert(
            $this->progressTable(),
            PaymentRequestProgress::of($paymentRequest)->row(),
            PaymentRequestProgress::TYPES,
        );
    }

    private function connection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    private function progressTable(): string
    {
        return $this->entityManager->getClassMetadata(self::PROGRESS)->getTableName();
    }

    private function detach(LocalPaymentRequestId $id): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $managed = $unitOfWork->tryGetById($id, self::ENTITY);
        if ($managed !== false) {
            $unitOfWork->detach($managed);
        }
    }

    private function isStored(LocalPaymentRequestId $id): bool
    {
        return $this->entityManager
            ->createQuery('SELECT p.id FROM ' . self::ENTITY . ' p WHERE p.id = :id')
            ->setParameter('id', $id, LocalPaymentRequestIdType::NAME)
            ->getScalarResult() !== [];
    }

    /**
     * Removes the stored request (and its managed copy) that an unmanaged aggregate of the same identity replaces; its
     * progress row is rewritten by {@see self::replaceProgress()}.
     */
    private function discard(LocalPaymentRequestId $id): void
    {
        $this->detach($id);
        $this->entityManager
            ->createQuery('DELETE FROM ' . self::ENTITY . ' p WHERE p.id = :id')
            ->setParameter('id', $id, LocalPaymentRequestIdType::NAME)
            ->execute();
    }

    /**
     * @throws InvariantViolation
     */
    private function assertUnique(PaymentRequest $paymentRequest): void
    {
        if ($this->exists($paymentRequest, 'p.invoice.number = :value', $paymentRequest->invoiceNumber()->value())) {
            throw InvariantViolation::because(
                'payment_request.duplicate_invoice_number',
                'A payment request already exists for this invoice number.',
                ['invoice_number' => $paymentRequest->invoiceNumber()->value()],
            );
        }
        if ($this->exists($paymentRequest, 'p.externalReference = :value', $paymentRequest->externalReference()->value())) {
            throw InvariantViolation::because(
                'payment_request.duplicate_external_reference',
                'A payment request already exists for this external reference.',
                ['external_reference' => $paymentRequest->externalReference()->value()],
            );
        }
    }

    private function exists(PaymentRequest $paymentRequest, string $condition, string $value): bool
    {
        return $this->entityManager
            ->createQuery('SELECT p.id FROM ' . self::ENTITY . ' p WHERE p.tenantId = :tenant AND p.id <> :id AND ' . $condition)
            ->setParameter('tenant', $paymentRequest->tenantId(), TenantIdType::NAME)
            ->setParameter('id', $paymentRequest->id(), LocalPaymentRequestIdType::NAME)
            ->setParameter('value', $value)
            ->getScalarResult() !== [];
    }
}
