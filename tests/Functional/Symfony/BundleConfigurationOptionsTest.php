<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class BundleConfigurationOptionsTest extends TestCase
{
    public function testTheLockModesAndTheSmallestTtlAreAccepted(): void
    {
        foreach (['auto', true, false] as $mode) {
            $harness = Harness::boot(['lock' => ['enabled' => $mode, 'ttl' => 1.0]], false, false, true);

            static::assertTrue($harness->hasPrivate(OperationLocks::class));
        }
    }

    public function testThePairingDateIsReadInTheConfiguredTimeZone(): void
    {
        $harness = Harness::boot(['pairing_time_zone' => 'America/Martinique']);

        $id = Scenario::registeredCustomer($harness);

        // The scripted description says "15/01/2022 11:00:00": French local time (10:00Z) by default.
        $view = $harness->get(GetCustomerRegistrationHandler::class)(new GetCustomerRegistration(TenantId::fromString('tenant-1'), CustomerSelector::byRegistrationId($id)));
        static::assertSame('2022-01-15T15:00:00Z', $view->pairingAcceptedAt);
    }
}
