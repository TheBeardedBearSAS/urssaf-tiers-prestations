<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository;

use BackedEnum;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\StateCount;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * One GROUP BY query per table (indexes "tenant_id, state, ..." of both tables).
 */
final readonly class DoctrineWorkflowStatistics implements WorkflowStatistics
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Override]
    public function registrationStates(): array
    {
        return $this->count(CustomerRegistration::class, 'status.state');
    }

    #[Override]
    public function paymentRequestStates(): array
    {
        return $this->count(PaymentRequest::class, 'state');
    }

    /**
     * @param class-string $entity
     *
     * @return list<StateCount>
     */
    private function count(string $entity, string $stateField): array
    {
        /** @var list<array{tenantId: TenantId, state: BackedEnum, total: int|string}> $rows */
        $rows = $this->entityManager
            ->createQuery(\sprintf('SELECT r.tenantId AS tenantId, r.%1$s AS state, COUNT(r.id) AS total FROM %2$s r GROUP BY r.tenantId, r.%1$s', $stateField, $entity))
            ->getResult();

        return array_map(
            static fn(array $row): StateCount => new StateCount($row['tenantId'], (string) $row['state']->value, (int) $row['total']),
            $rows,
        );
    }
}
