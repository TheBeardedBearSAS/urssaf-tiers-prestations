<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrinePaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Contract\WorkflowStatisticsContract;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\TestDatabase;

final class DoctrineWorkflowStatisticsTest extends TestCase
{
    use WorkflowStatisticsContract;

    protected function createAdapters(): array
    {
        $entityManager = TestDatabase::entityManager();

        return [
            new DoctrineWorkflowStatistics($entityManager),
            new DoctrineCustomerRegistrationRepository($entityManager),
            new DoctrinePaymentRequestRepository($entityManager),
        ];
    }
}
