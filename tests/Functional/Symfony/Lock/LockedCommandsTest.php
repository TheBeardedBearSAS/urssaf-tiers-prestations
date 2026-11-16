<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Lock;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HeldLocks;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class LockedCommandsTest extends TestCase
{
    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('commands')]
    public function testACommandIsSkippedWhenItsOperationIsAlreadyRunning(string $command, array $options, string $resource): void
    {
        $harness = Harness::boot([], false, false, true);
        Scenario::pendingRegistration($harness);
        Scenario::pendingPaymentRequest($harness);
        $held = HeldLocks::hold($harness, $resource);
        $setupRequests = \count($harness->client->getRequests());

        $tester = $harness->command($command);

        static::assertSame(0, $tester->execute($options));
        static::assertStringContainsString(\sprintf('Already running (lock "%s" is held): skipped.', $resource), $tester->getDisplay());
        static::assertCount($setupRequests, $harness->client->getRequests());
        static::assertTrue($held->isAcquired());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function commands(): iterable
    {
        yield 'submit customers' => ['urssaf:customers:submit-pending', [], 'urssaf_tps.customers.submit.tenant-1'];
        yield 'submit payment requests' => ['urssaf:payment-requests:submit-pending', [], 'urssaf_tps.payment-requests.submit.tenant-1'];
        yield 'sync pairing of a tenant' => ['urssaf:customers:sync-pairing', ['--tenant' => 'tenant-1'], 'urssaf_tps.customers.sync-pairing.tenant-1'];
        yield 'sync pairing of every tenant' => ['urssaf:customers:sync-pairing', [], 'urssaf_tps.customers.sync-pairing.*'];
        yield 'sync statuses of a tenant' => ['urssaf:payment-requests:sync-statuses', ['--tenant' => 'tenant-1'], 'urssaf_tps.payment-requests.sync-statuses.tenant-1'];
        yield 'sync statuses of every tenant' => ['urssaf:payment-requests:sync-statuses', [], 'urssaf_tps.payment-requests.sync-statuses.*'];
        yield 'reconcile' => ['urssaf:payment-requests:reconcile-stale', [], 'urssaf_tps.payment-requests.reconcile-stale.*'];
        yield 'requeue' => ['urssaf:requeue-invalid-credentials', ['--tenant' => 'tenant-1'], 'urssaf_tps.requeue-invalid-credentials.tenant-1'];
    }

    public function testAnotherTenantIsStillProcessed(): void
    {
        // @mago-expect lint:no-literal-password The secret is an environment placeholder, set by the test kernel.
        $harness = Harness::boot(['tenants' => ['tenant-1', 'tenant-2'], 'credentials' => ['tenant-2' => ['client_id' => 'client-2', 'client_secret' => '%env(URSSAF_TEST_SECRET)%']]], false, false, true);
        $id = Scenario::pendingRegistration($harness, 'tenant-2', 'customer-2');
        $held = HeldLocks::hold($harness, 'urssaf_tps.customers.submit.tenant-1');

        $tester = $harness->command('urssaf:customers:submit-pending');

        static::assertSame(0, $tester->execute([]));
        static::assertTrue($held->isAcquired());
        static::assertStringContainsString('Already running (lock "urssaf_tps.customers.submit.tenant-1"', $tester->getDisplay());
        static::assertStringContainsString($id->value(), $tester->getDisplay());
        static::assertStringContainsString('1 record(s) processed', $tester->getDisplay());
    }

    public function testACommandRunsAndReleasesItsLockWhenFree(): void
    {
        $harness = Harness::boot([], false, false, true);
        $id = Scenario::pendingRegistration($harness);

        $tester = $harness->command('urssaf:customers:submit-pending');

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString($id->value(), $tester->getDisplay());
        static::assertStringNotContainsString('Already running', $tester->getDisplay());
        static::assertTrue(HeldLocks::factory($harness)->createLock('urssaf_tps.customers.submit.tenant-1')->acquire());
    }

    public function testADryRunDoesNotTakeTheLock(): void
    {
        $harness = Harness::boot([], false, false, true);
        $id = Scenario::pendingRegistration($harness);
        $held = HeldLocks::hold($harness, 'urssaf_tps.customers.submit.tenant-1');

        $tester = $harness->command('urssaf:customers:submit-pending');

        static::assertSame(0, $tester->execute(['--dry-run' => true]));
        static::assertStringContainsString($id->value(), $tester->getDisplay());
        static::assertTrue($held->isAcquired());
        static::assertStringNotContainsString('Already running', $tester->getDisplay());
    }

    public function testTheLockCanBeDisabled(): void
    {
        $harness = Harness::boot(['lock' => ['enabled' => false]], false, false, true);
        $id = Scenario::pendingRegistration($harness);
        $held = HeldLocks::hold($harness, 'urssaf_tps.customers.submit.tenant-1');

        $tester = $harness->command('urssaf:customers:submit-pending');

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString($id->value(), $tester->getDisplay());
        static::assertTrue($held->isAcquired());
        static::assertStringNotContainsString('Already running', $tester->getDisplay());
    }

    public function testTheLockIsOptionalWhenTheFactoryDoesNotExist(): void
    {
        $harness = Harness::boot(['lock' => ['factory' => 'my.lock_factory']]);
        $id = Scenario::pendingRegistration($harness);

        $tester = $harness->command('urssaf:customers:submit-pending');

        static::assertSame(0, $tester->execute([]));
        static::assertStringContainsString($id->value(), $tester->getDisplay());
    }

    public function testAnExplicitlyEnabledLockNeedsItsFactory(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('my.lock_factory');

        Harness::boot(['lock' => ['enabled' => true, 'factory' => 'my.lock_factory']], false, false, true);
    }
}
