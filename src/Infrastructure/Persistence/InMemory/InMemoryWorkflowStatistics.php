<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory;

use Override;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\StateCount;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Counts the records of the in-memory repositories.
 */
final readonly class InMemoryWorkflowStatistics implements WorkflowStatistics
{
    public function __construct(
        private InMemoryCustomerRegistrationRepository $registrations,
        private InMemoryPaymentRequestRepository $paymentRequests,
    ) {}

    #[Override]
    public function registrationStates(): array
    {
        return self::count(array_map(
            static fn(CustomerRegistration $registration): array => [$registration->tenantId(), $registration->status()->state()->value],
            $this->registrations->all(),
        ));
    }

    #[Override]
    public function paymentRequestStates(): array
    {
        return self::count(array_map(
            static fn(PaymentRequest $paymentRequest): array => [$paymentRequest->tenantId(), $paymentRequest->state()->value],
            $this->paymentRequests->all(),
        ));
    }

    /**
     * @param list<array{TenantId, string}> $records tenant and state of each record
     *
     * @return list<StateCount>
     */
    private static function count(array $records): array
    {
        $tenants = [];
        $counts = [];
        foreach ($records as [$tenantId, $state]) {
            $tenants[$tenantId->value()] = $tenantId;
            $counts[$tenantId->value()][$state] = ($counts[$tenantId->value()][$state] ?? 0) + 1;
        }
        $stateCounts = [];
        foreach ($counts as $tenant => $byState) {
            foreach ($byState as $state => $count) {
                $stateCounts[] = new StateCount($tenants[$tenant], $state, $count);
            }
        }

        return $stateCounts;
    }
}
