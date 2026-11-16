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

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\ConfigurationRules;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;

final class OperationLocksTest extends TestCase
{
    public function testTheResourceNameContainsTheOperationAndTheScope(): void
    {
        static::assertSame('urssaf_tps.payment-requests.submit.tenant-1', OperationLocks::resource('payment-requests.submit', 'tenant-1'));
    }

    public function testItRunsTheActionWithoutFactory(): void
    {
        $result = (new OperationLocks(null))->exclusively('op', 'tenant-1', static fn(): string => 'ran', static fn(): string => 'busy');

        static::assertSame('ran', $result);
    }

    public function testItRunsTheActionAndReleasesTheLock(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $locks = new OperationLocks($factory, 60.0);
        $held = null;

        $result = $locks->exclusively('op', 'tenant-1', static function () use ($factory, &$held): string {
            $held = !$factory->createLock(OperationLocks::resource('op', 'tenant-1'))->acquire();

            return 'ran';
        }, static fn(): string => 'busy');

        static::assertSame('ran', $result);
        static::assertTrue($held);
        static::assertTrue($factory->createLock(OperationLocks::resource('op', 'tenant-1'))->acquire());
    }

    public function testItNeverWaitsForTheLock(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects(static::once())->method('acquire')->with(false)->willReturn(false);
        $lock->expects(static::never())->method('release');
        $factory = $this->createMock(LockFactory::class);
        $factory->expects(static::once())->method('createLock')->with('urssaf_tps.op.tenant-1', 90.5)->willReturn($lock);

        $result = (new OperationLocks($factory, 90.5))->exclusively('op', 'tenant-1', static fn(): string => 'ran', static fn(): string => 'busy');

        static::assertSame('busy', $result);
    }

    public function testItReleasesTheLockAfterTheAction(): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lock->expects(static::once())->method('release');
        $factory = $this->createMock(LockFactory::class);
        $factory->method('createLock')->willReturn($lock);

        $result = (new OperationLocks($factory))->exclusively('op', 'tenant-1', static fn(): string => 'ran', static fn(): string => 'busy');

        static::assertSame('ran', $result);
    }

    public function testItReleasesTheLockWhenTheActionFails(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $locks = new OperationLocks($factory);

        try {
            $locks->exclusively('op', 'tenant-1', static function (): never {
                throw new LogicException('boom');
            }, static fn(): string => 'busy');
            static::fail('The failure must propagate.');
        } catch (LogicException) {
            static::assertTrue($factory->createLock(OperationLocks::resource('op', 'tenant-1'))->acquire());
        }
    }

    public function testItReportsTheLockNameWhenAnotherProcessHoldsIt(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $other = $factory->createLock(OperationLocks::resource('op', 'tenant-1'));
        static::assertTrue($other->acquire());
        $ran = false;

        $result = (new OperationLocks($factory))->exclusively('op', 'tenant-1', static function () use (&$ran): string {
            $ran = true;

            return 'ran';
        }, static fn(string $resource): string => 'busy:' . $resource);

        static::assertSame('busy:urssaf_tps.op.tenant-1', $result);
        static::assertFalse($ran);
        static::assertTrue($other->isAcquired(), 'The lock of the other process is left untouched.');
    }

    public function testAnotherTenantIsNotBlocked(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $factory->createLock(OperationLocks::resource('op', 'tenant-1'))->acquire();

        $result = (new OperationLocks($factory))->exclusively('op', 'tenant-2', static fn(): string => 'ran', static fn(): string => 'busy');

        static::assertSame('ran', $result);
    }

    public function testTheLockRuleOnlyFailsWhenTheLockIsRequiredAndMissing(): void
    {
        static::assertTrue(ConfigurationRules::lockRequiredButMissing(['lock' => ['enabled' => true]], false));
        static::assertFalse(ConfigurationRules::lockRequiredButMissing(['lock' => ['enabled' => true]], true));
        static::assertFalse(ConfigurationRules::lockRequiredButMissing(['lock' => ['enabled' => 'auto']], false));
        static::assertFalse(ConfigurationRules::lockRequiredButMissing(['lock' => ['enabled' => false]], false));
        static::assertFalse(ConfigurationRules::lockEnabledWithoutComponent(['lock' => ['enabled' => true]]));
    }
}
