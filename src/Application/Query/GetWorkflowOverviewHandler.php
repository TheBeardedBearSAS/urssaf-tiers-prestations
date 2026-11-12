<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Query;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\StateCount;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * The records by tenant and state, for dashboards and alerting.
 */
final readonly class GetWorkflowOverviewHandler
{
    public function __construct(private WorkflowStatistics $statistics) {}

    /**
     * @return list<TenantOverview> one entry per tenant (sorted by id) that has records, or exactly one for the requested tenant
     */
    public function __invoke(GetWorkflowOverview $query): array
    {
        $registrations = $this->byTenant($this->statistics->registrationStates(), $query);
        $paymentRequests = $this->byTenant($this->statistics->paymentRequestStates(), $query);
        $tenants = $query->tenantId instanceof TenantId ? [$query->tenantId->value()] : array_keys($registrations + $paymentRequests);
        sort($tenants, SORT_STRING);

        return array_map(static fn(string $tenant): TenantOverview => new TenantOverview(
            $tenant,
            [...self::zeros(RegistrationState::cases()), ...$registrations[$tenant] ?? []],
            [...self::zeros(SubmissionState::cases()), ...$paymentRequests[$tenant] ?? []],
        ), $tenants);
    }

    /**
     * @param list<StateCount> $counts
     *
     * @return array<string, array<string, int>>
     */
    private function byTenant(array $counts, GetWorkflowOverview $query): array
    {
        $byTenant = [];
        foreach ($counts as $count) {
            if ($query->tenantId instanceof TenantId && !$count->tenantId->equals($query->tenantId)) {
                continue;
            }
            $byTenant[$count->tenantId->value()][$count->state] = $count->count;
        }

        return $byTenant;
    }

    /**
     * @param list<RegistrationState>|list<SubmissionState> $states
     *
     * @return array<string, int>
     */
    private static function zeros(array $states): array
    {
        return array_fill_keys(array_map(static fn(RegistrationState|SubmissionState $state): string => $state->value, $states), 0);
    }
}
