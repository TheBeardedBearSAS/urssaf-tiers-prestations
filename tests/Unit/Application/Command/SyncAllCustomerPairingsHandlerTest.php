<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllCustomerPairings;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllCustomerPairingsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class SyncAllCustomerPairingsHandlerTest extends TestCase
{
    public function testSyncsEveryTenantAndReportsRefusedCredentials(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::AwaitingPairing);
        $harness->registration(2, RegistrationState::AwaitingPairing, Harness::OTHER_TENANT);
        $harness->registration(3, RegistrationState::AwaitingPairing, 'tenant-3');
        $harness->registration(4, RegistrationState::AwaitingPairing, 'tenant-3');
        $harness->gateway->willReportStatus(Harness::urssafCustomerId(1), UrssafAuthenticationFailed::withStatus(401));
        PairingScenario::willReport($harness, 2, 'APPAREILLAGE_EC');
        PairingScenario::willReport($harness, 3, 'APPAREILLAGE_EC');
        PairingScenario::willReport($harness, 4, 'APPAREILLAGE_EC');
        $handler = new SyncAllCustomerPairingsHandler($harness->registrations, PairingScenario::handler($harness));

        $report = $handler(new SyncAllCustomerPairings(1));

        static::assertEquals([
            new BatchItem('tenant-1', ItemOutcome::InvalidCredentials),
            new BatchItem('reg-2', ItemOutcome::Unchanged, ['APPAREILLAGE_EC']),
            new BatchItem('reg-3', ItemOutcome::Unchanged, ['APPAREILLAGE_EC']),
        ], $report->items());
        static::assertSame(['tenant-1', 'tenant-2', 'tenant-3'], $harness->gateway->tenants());
    }
}
