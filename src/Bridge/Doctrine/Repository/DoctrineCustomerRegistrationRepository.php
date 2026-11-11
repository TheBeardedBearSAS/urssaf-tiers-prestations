<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\ExternalReferenceType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\RegistrationIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\TenantIdType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\UrssafCustomerIdType;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Doctrine ORM implementation of {@see CustomerRegistrationRepository}, with the semantics of the in-memory one:
 * lists are ordered by last update (oldest first, then creation, then id), uniqueness violations are reported as
 * {@see InvariantViolation} with the same codes.
 *
 * Unit of work: {@see self::save()} persists and flushes immediately (a flush writes every dirty managed entity), so a
 * save inside a {@see DoctrineTransactionManager} transaction is durable only when that
 * transaction commits. An aggregate that is not managed (created by the caller, or detached by clear()) replaces the stored row of the same
 * identity, like a save in the in-memory repository. Reads return the managed instance, so
 * unlike the in-memory repository a change is visible to every holder of the instance before it is saved.
 *
 * Claiming: {@see self::claimForSubmission()} is one conditional UPDATE, never an object round-trip.
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness and claim helpers.
 * @mago-expect lint:too-many-methods
 */
final readonly class DoctrineCustomerRegistrationRepository implements CustomerRegistrationRepository
{
    private const ENTITY = CustomerRegistration::class;

    private const DUPLICATE_EXTERNAL_REFERENCE = 'customer_registration.duplicate_external_reference';

    private const DUPLICATE_URSSAF_CUSTOMER_ID = 'customer_registration.duplicate_urssaf_customer_id';

    private const BY_UPDATE = ' ORDER BY r.status.updatedAt ASC, r.createdAt ASC, r.id ASC';

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws InvariantViolation "customer_registration.duplicate_external_reference" or
     *                            "customer_registration.duplicate_urssaf_customer_id"
     */
    public function save(CustomerRegistration $registration): void
    {
        $this->assertUnique($registration);
        $replaces = !$this->entityManager->contains($registration) && $this->isStored($registration->id());
        try {
            $this->entityManager->getConnection()->transactional(function () use ($registration, $replaces): void {
                if ($replaces) {
                    $this->discard($registration->id());
                }
                $this->entityManager->persist($registration);
                $this->entityManager->flush();
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent writer won the race between the check above and the flush.
            $this->assertUnique($registration);

            throw $exception;
        }
    }

    public function get(RegistrationId $id): CustomerRegistration
    {
        return $this->entityManager->find(self::ENTITY, $id) ?? throw RecordNotFound::withIdentifier(
            'customer_registration',
            $id->value(),
        );
    }

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?CustomerRegistration
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery('SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.externalReference = :reference')
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('reference', $externalReference, ExternalReferenceType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function findByUrssafCustomerId(TenantId $tenantId, UrssafCustomerId $urssafCustomerId): ?CustomerRegistration
    {
        return Hydration::first(
            $this->entityManager
                ->createQuery('SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.status.urssafCustomerId = :urssafId')
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('urssafId', $urssafCustomerId, UrssafCustomerIdType::NAME)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function findPairingToFollow(TenantId $tenantId, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery('SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.status.state IN (:states)' . self::BY_UPDATE)
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('states', self::statesWithPairingToFollow(), ArrayParameterType::STRING)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery(
                    'SELECT r FROM ' . self::ENTITY . ' r WHERE r.status.state = :submitting AND r.status.lastAttemptAt <= :olderThan'
                    . ' ORDER BY r.status.lastAttemptAt ASC, r.createdAt ASC, r.id ASC',
                )
                ->setParameter('submitting', RegistrationState::Submitting)
                ->setParameter('olderThan', $olderThan, InstantType::NAME)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function findByState(TenantId $tenantId, RegistrationState $state, int $limit): array
    {
        return Hydration::all(
            $this->entityManager
                ->createQuery('SELECT r FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.status.state = :state' . self::BY_UPDATE)
                ->setParameter('tenant', $tenantId, TenantIdType::NAME)
                ->setParameter('state', $state)
                ->setMaxResults($limit)
                ->toIterable(),
            self::ENTITY,
        );
    }

    public function tenantsWithPairingToFollow(): array
    {
        /** @var list<string> $tenants */
        $tenants = $this->entityManager
            ->createQuery(
                'SELECT r.tenantId, MIN(r.status.updatedAt) AS HIDDEN oldestUpdate FROM ' . self::ENTITY . ' r'
                . ' WHERE r.status.state IN (:states) GROUP BY r.tenantId ORDER BY oldestUpdate ASC, r.tenantId ASC',
            )
            ->setParameter('states', self::statesWithPairingToFollow(), ArrayParameterType::STRING)
            ->getSingleColumnResult();

        return array_map(TenantId::fromString(...), $tenants);
    }

    public function delete(RegistrationId $id): void
    {
        $this->discard($id);
    }

    public function claimForSubmission(RegistrationId $id, Instant $now): bool
    {
        $claimed = $this->entityManager
            ->createQuery(
                'UPDATE ' . self::ENTITY . ' r SET r.status.state = :submitting, r.status.updatedAt = :now,'
                . ' r.status.lastAttemptAt = :now, r.status.attempts = r.status.attempts + 1'
                . ' WHERE r.id = :id AND r.status.state = :pending',
            )
            ->setParameter('submitting', RegistrationState::Submitting)
            ->setParameter('pending', RegistrationState::Pending)
            ->setParameter('now', $now, InstantType::NAME)
            ->setParameter('id', $id, RegistrationIdType::NAME)
            ->execute() === 1;
        if ($claimed) {
            // The managed copy (if any) is now stale: drop it so that the next get() reads the claimed row.
            $this->detach($id);
        }

        return $claimed;
    }

    /**
     * The raw values of the states whose pairing status must be polled, derived from the domain enum so that the
     * SQL selection cannot drift from {@see RegistrationState::hasPairingToFollow()}.
     *
     * @return list<string>
     */
    private static function statesWithPairingToFollow(): array
    {
        $states = [];
        foreach (RegistrationState::cases() as $state) {
            if (!$state->hasPairingToFollow()) {
                continue;
            }
            $states[] = $state->value;
        }

        return $states;
    }

    private function detach(RegistrationId $id): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $managed = $unitOfWork->tryGetById($id, self::ENTITY);
        if ($managed !== false) {
            $unitOfWork->detach($managed);
        }
    }

    private function isStored(RegistrationId $id): bool
    {
        return $this->entityManager
            ->createQuery('SELECT r.id FROM ' . self::ENTITY . ' r WHERE r.id = :id')
            ->setParameter('id', $id, RegistrationIdType::NAME)
            ->getScalarResult() !== [];
    }

    /**
     * Removes the stored registration (and its managed copy): deletion, or replacement by an unmanaged aggregate of the
     * same identity.
     */
    private function discard(RegistrationId $id): void
    {
        $this->detach($id);
        $this->entityManager
            ->createQuery('DELETE FROM ' . self::ENTITY . ' r WHERE r.id = :id')
            ->setParameter('id', $id, RegistrationIdType::NAME)
            ->execute();
    }

    /**
     * @throws InvariantViolation
     */
    private function assertUnique(CustomerRegistration $registration): void
    {
        if ($this->exists($registration, 'r.externalReference = :value', $registration->externalReference()->value())) {
            throw InvariantViolation::because(
                self::DUPLICATE_EXTERNAL_REFERENCE,
                'A customer registration already exists for this external reference.',
                ['external_reference' => $registration->externalReference()->value()],
            );
        }
        $urssafId = $registration->status()->urssafCustomerId();
        if ($urssafId instanceof UrssafCustomerId && $this->exists($registration, 'r.status.urssafCustomerId = :value', $urssafId->value())) {
            throw InvariantViolation::because(
                self::DUPLICATE_URSSAF_CUSTOMER_ID,
                'A customer registration already exists for this URSSAF customer id.',
                ['urssaf_customer_id' => $urssafId->value()],
            );
        }
    }

    private function exists(CustomerRegistration $registration, string $condition, string $value): bool
    {
        return $this->entityManager
            ->createQuery('SELECT r.id FROM ' . self::ENTITY . ' r WHERE r.tenantId = :tenant AND r.id <> :id AND ' . $condition)
            ->setParameter('tenant', $registration->tenantId(), TenantIdType::NAME)
            ->setParameter('id', $registration->id(), RegistrationIdType::NAME)
            ->setParameter('value', $value)
            ->getScalarResult() !== [];
    }
}
