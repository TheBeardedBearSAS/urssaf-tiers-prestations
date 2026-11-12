<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\StateCount;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\WorkflowStatistics;

final readonly class FixedWorkflowStatistics implements WorkflowStatistics
{
    /**
     * @param list<StateCount> $registrations
     * @param list<StateCount> $paymentRequests
     */
    public function __construct(private array $registrations, private array $paymentRequests) {}

    public function registrationStates(): array
    {
        return $this->registrations;
    }

    public function paymentRequestStates(): array
    {
        return $this->paymentRequests;
    }
}
