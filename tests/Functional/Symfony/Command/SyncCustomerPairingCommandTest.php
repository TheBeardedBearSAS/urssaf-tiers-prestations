<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class SyncCustomerPairingCommandTest extends TestCase
{
    private const COMMAND = 'urssaf:customers:sync-pairing';

    public function testDryRunListsTheRegistrationsAwaitingThePairingOfEveryTenant(): void
    {
        $harness = Harness::boot();
        $id = Scenario::submittedRegistration($harness);

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--dry-run' => true]));
        static::assertSame([['Tenant', 'Record'], ['tenant-1', $id->value()]], Output::rows($tester->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s)', $tester->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_PAIRING));
    }

    public function testDryRunCanBeLimitedToATenant(): void
    {
        $harness = Harness::boot();
        Scenario::submittedRegistration($harness);

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--dry-run' => true, '--tenant' => 'tenant-2']));
        static::assertStringContainsString('Dry run: 0 record(s)', $tester->getDisplay());
        static::assertSame(0, $tester->execute(['--dry-run' => true, '--tenant' => 'tenant-1', '--limit' => '5']));
        static::assertStringContainsString('Dry run: 1 record(s)', $tester->getDisplay());
    }

    public function testSynchronisesEveryTenant(): void
    {
        $harness = Harness::boot();
        $id = Scenario::submittedRegistration($harness);

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('| ' . $id->value(), $tester->getDisplay());
        static::assertStringContainsString('changed', $tester->getDisplay());
        static::assertSame('registered', self::state($harness, $id));
    }

    public function testSynchronisesOneTenant(): void
    {
        $harness = Harness::boot();
        $id = Scenario::submittedRegistration($harness);

        $tester = $harness->command(self::COMMAND);

        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-2']));
        static::assertSame('awaiting_pairing', self::state($harness, $id));
        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1', '--limit' => '10']));
        static::assertSame('registered', self::state($harness, $id));
    }

    public function testATemporaryFailureFailsTheCommand(): void
    {
        $harness = Harness::boot();
        Scenario::submittedRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_PAIRING, 503, '{}');

        $tester = $harness->command(self::COMMAND);

        static::assertSame(1, $tester->execute(['--tenant' => 'tenant-1']));
        static::assertStringContainsString('is temporarily unavailable (HTTP 503)', $tester->getDisplay());
    }

    private static function state(Harness $harness, RegistrationId $id): string
    {
        return $harness->get(GetCustomerRegistrationHandler::class)(
            new GetCustomerRegistration(TenantId::fromString('tenant-1'), CustomerSelector::byRegistrationId($id)),
        )->state;
    }
}
