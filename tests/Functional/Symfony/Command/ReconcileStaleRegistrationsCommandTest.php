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
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class ReconcileStaleRegistrationsCommandTest extends TestCase
{
    private const RECONCILE = 'urssaf:customers:reconcile-stale';

    public function testNothingIsStaleRightAfterTheSubmission(): void
    {
        $harness = Harness::boot();
        Scenario::submittedRegistration($harness);

        $tester = $harness->command(self::RECONCILE);

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString('0 record(s) processed.', $tester->getDisplay());
    }

    public function testSettlesTheSubmissionsLeftWithoutAnswerForAManualReview(): void
    {
        $harness = Harness::boot();
        $id = Scenario::pendingRegistration($harness);
        // A worker claimed the registration, then crashed before method 010 answered: it stays "submitting".
        static::assertTrue($harness->privateService(CustomerRegistrationRepository::class, CustomerRegistrationRepository::class)->claimForSubmission($id, Instant::fromDateTime($harness->clock->now())));
        static::assertSame('submitting', self::state($harness, $id));

        $reconcile = $harness->command(self::RECONCILE);
        static::assertSame(0, $reconcile->execute(['--dry-run' => true]));
        static::assertStringContainsString('Dry run: 0 record(s)', $reconcile->getDisplay());

        $harness->clock->sleep(20 * 60);
        static::assertSame(0, $reconcile->execute(['--dry-run' => true]));
        static::assertSame([['Tenant', 'Record'], ['tenant-1', $id->value()]], Output::rows($reconcile->getDisplay()));
        static::assertSame('submitting', self::state($harness, $id));
        $registerCalls = $harness->urssaf->calls(HttpScript::ROUTE_REGISTER);

        static::assertSame(1, $reconcile->execute(['--limit' => '4']));
        static::assertStringContainsString('manual_review', $reconcile->getDisplay());
        static::assertSame('error', self::state($harness, $id));
        static::assertSame($registerCalls, $harness->urssaf->calls(HttpScript::ROUTE_REGISTER), 'Nothing is sent again.');
    }

    private static function state(Harness $harness, RegistrationId $id): string
    {
        return $harness->get(GetCustomerRegistrationHandler::class)(
            new GetCustomerRegistration(TenantId::fromString('tenant-1'), CustomerSelector::byRegistrationId($id)),
        )->state;
    }
}
