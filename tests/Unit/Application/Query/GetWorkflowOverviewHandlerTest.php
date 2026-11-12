<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\StateCount;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\FixedWorkflowStatistics;

final class GetWorkflowOverviewHandlerTest extends TestCase
{
    private const NO_REGISTRATION = ['pending' => 0, 'submitting' => 0, 'awaiting_pairing' => 0, 'registered' => 0, 'rejected' => 0, 'blocked' => 0, 'mandate_expired' => 0, 'error' => 0, 'invalid_credentials' => 0];
    private const NO_PAYMENT_REQUEST = ['pending' => 0, 'submitting' => 0, 'submitted' => 0, 'rejected' => 0, 'error' => 0, 'invalid_credentials' => 0];

    public function testEveryTenantWithRecordsIsListedInOrderWithEveryState(): void
    {
        $overviews = self::handler()(GetWorkflowOverview::allTenants());

        static::assertCount(3, $overviews);
        static::assertSame(['tenant-a', 'tenant-b', 'tenant-c'], array_map(static fn(TenantOverview $overview): string => $overview->tenantId, $overviews));
        static::assertSame(array_replace(self::NO_REGISTRATION, ['registered' => 2, 'error' => 1]), $overviews[0]->registrations);
        static::assertSame(array_keys(self::NO_REGISTRATION), array_keys($overviews[0]->registrations));
        static::assertSame(array_replace(self::NO_PAYMENT_REQUEST, ['submitted' => 4, 'invalid_credentials' => 1]), $overviews[0]->paymentRequests);
        static::assertSame(array_keys(self::NO_PAYMENT_REQUEST), array_keys($overviews[0]->paymentRequests));
        static::assertSame(self::NO_REGISTRATION, $overviews[1]->registrations);
        static::assertSame(array_replace(self::NO_PAYMENT_REQUEST, ['pending' => 3]), $overviews[1]->paymentRequests);
        static::assertSame(array_replace(self::NO_REGISTRATION, ['invalid_credentials' => 1]), $overviews[2]->registrations);
    }

    public function testOneTenantIsListedEvenWithoutRecords(): void
    {
        $handler = self::handler();

        $known = $handler(GetWorkflowOverview::forTenant(TenantId::fromString('tenant-b')));
        $unknown = $handler(GetWorkflowOverview::forTenant(TenantId::fromString('tenant-z')));

        static::assertCount(1, $known);
        static::assertSame('tenant-b', $known[0]->tenantId);
        static::assertSame(array_replace(self::NO_PAYMENT_REQUEST, ['pending' => 3]), $known[0]->paymentRequests);
        static::assertSame(self::NO_REGISTRATION, $known[0]->registrations);
        static::assertCount(1, $unknown);
        static::assertSame('tenant-z', $unknown[0]->tenantId);
        static::assertSame(self::NO_REGISTRATION, $unknown[0]->registrations);
        static::assertSame(self::NO_PAYMENT_REQUEST, $unknown[0]->paymentRequests);
    }

    public function testTheRecordsWaitingForAManualActionAreSummed(): void
    {
        $overviews = (new GetWorkflowOverviewHandler(new FixedWorkflowStatistics(
            [self::stateCount('t', 'error', 1), self::stateCount('t', 'invalid_credentials', 2), self::stateCount('t', 'rejected', 50)],
            [self::stateCount('t', 'error', 4), self::stateCount('t', 'invalid_credentials', 8), self::stateCount('t', 'pending', 50)],
        )))(GetWorkflowOverview::allTenants());

        static::assertSame(15, $overviews[0]->needingAttention());
        static::assertSame([
            'tenant_id' => 't',
            'registrations' => $overviews[0]->registrations,
            'payment_requests' => $overviews[0]->paymentRequests,
            'needing_attention' => 15,
        ], $overviews[0]->toArray());
    }

    private static function handler(): GetWorkflowOverviewHandler
    {
        return new GetWorkflowOverviewHandler(new FixedWorkflowStatistics(
            [self::stateCount('tenant-c', 'invalid_credentials', 1), self::stateCount('tenant-a', 'registered', 2), self::stateCount('tenant-a', 'error', 1)],
            [self::stateCount('tenant-a', 'submitted', 4), self::stateCount('tenant-b', 'pending', 3), self::stateCount('tenant-a', 'invalid_credentials', 1)],
        ));
    }

    private static function stateCount(string $tenant, string $state, int $count): StateCount
    {
        return new StateCount(TenantId::fromString($tenant), $state, $count);
    }
}
