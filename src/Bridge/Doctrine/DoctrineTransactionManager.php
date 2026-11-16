<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Entity\PaymentRequestProgress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use Throwable;

/**
 * {@see TransactionManager} on the DBAL connection of an entity manager.
 *
 * The repositories of this bridge persist and flush on every save: inside {@see self::transactional()} those flushes
 * join the surrounding transaction (nested calls just join the outermost one) and become durable together when the
 * operation returns. When it throws, the transaction is rolled back, the entities of this package that the entity
 * manager still manages ({@see CustomerRegistration}, {@see PaymentRequest}, {@see PaymentRequestProgress}) are detached
 * (so none that was flushed inside the rolled-back transaction stays managed) and the throwable is rethrown unchanged.
 * The entities of the host application are left untouched: the entity manager is never cleared as a whole.
 *
 * Unlike EntityManager::wrapInTransaction(), a failing operation does not close the entity manager: domain exceptions
 * (invalid state transition, duplicate...) are ordinary control flow for the application layer.
 */
final readonly class DoctrineTransactionManager implements TransactionManager
{
    /** Root classes of the identity map entries that belong to this package. */
    private const PACKAGE_ENTITIES = [CustomerRegistration::class, PaymentRequest::class, PaymentRequestProgress::class];

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @template T
     *
     * @param callable(): T $operation
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        try {
            return $this->entityManager->getConnection()->transactional(static fn(): mixed => $operation());
        } catch (Throwable $throwable) {
            $this->detachPackageEntities();

            throw $throwable;
        }
    }

    private function detachPackageEntities(): void
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $class => $entities) {
            if (!\in_array($class, self::PACKAGE_ENTITIES, true)) {
                continue;
            }
            foreach ($entities as $entity) {
                $unitOfWork->detach($entity);
            }
        }
    }
}
