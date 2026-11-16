<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

use PHPUnit\Framework\Assert;
use ReflectionClass;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * The retry delay of a recoverable exception only exists with symfony/messenger versions that support it.
 */
final class RetryDelay
{
    private function __construct() {}

    public static function isSupported(): bool
    {
        return (new ReflectionClass(RecoverableMessageHandlingException::class))->hasMethod('getRetryDelay');
    }

    /**
     * Asserts the delay (in milliseconds) carried by the exception, whatever the installed messenger version.
     */
    public static function assertCarries(?int $expected, RecoverableMessageHandlingException $failure): void
    {
        if (!self::isSupported()) {
            Assert::assertFalse(self::isSupported());

            return;
        }

        Assert::assertSame($expected, $failure->getRetryDelay());
    }
}
