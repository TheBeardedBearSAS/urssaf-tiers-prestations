<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Transaction;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Transaction\ImmediateTransactionManager;

final class ImmediateTransactionManagerTest extends TestCase
{
    public function testReturnsTheOperationResult(): void
    {
        $result = (new ImmediateTransactionManager())->transactional(static fn(): int => 42);

        static::assertSame(42, $result);
    }

    public function testRunsTheOperationExactlyOnce(): void
    {
        $calls = 0;

        (new ImmediateTransactionManager())->transactional(
            static function () use (&$calls): void {
                ++$calls;
            },
        );

        static::assertSame(1, $calls);
    }

    public function testRethrowsTheOperationFailureUnchanged(): void
    {
        $failure = new RuntimeException('boom');

        $this->expectExceptionObject($failure);

        (new ImmediateTransactionManager())->transactional(static function () use ($failure): never {
            throw $failure;
        });
    }
}
