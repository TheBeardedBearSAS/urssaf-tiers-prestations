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
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;

final class InMemoryWorkflowStatisticsContractTest extends TestCase
{
    use WorkflowStatisticsContract;

    protected function createAdapters(): array
    {
        $registrations = new InMemoryCustomerRegistrationRepository();
        $paymentRequests = new InMemoryPaymentRequestRepository();

        return [new InMemoryWorkflowStatistics($registrations, $paymentRequests), $registrations, $paymentRequests];
    }
}
