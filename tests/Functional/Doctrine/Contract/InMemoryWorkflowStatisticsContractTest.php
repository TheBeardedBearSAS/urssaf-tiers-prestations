<?php

declare(strict_types=1);

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
