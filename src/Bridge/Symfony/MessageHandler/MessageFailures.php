<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler;

use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\UrssafException;
use Throwable;

/**
 * Translates the failures of the use cases into the Messenger retry semantics:
 * - temporary failures ({@see RetryableException}) are recoverable: the message is retried, after the delay requested
 *   by URSSAF or by the local rate limiter when known;
 * - any other package failure is unrecoverable: retrying the same message cannot succeed;
 * - an operation already running for the tenant (lock held) is recoverable too: the message is retried later;
 * - failures of other origins (database down...) are left to the retry strategy of the host.
 */
final class MessageFailures
{
    /** Delay before a message is handled again when its operation was already running. */
    private const BUSY_RETRY_DELAY_MILLISECONDS = 30_000;

    /**
     * @template T
     *
     * @param callable(): T $operation
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     *
     * @throws RecoverableMessageHandlingException
     * @throws UnrecoverableMessageHandlingException
     */
    public static function guard(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RetryableException $failure) {
            throw RecoverableFailures::detect()->create($failure->getMessage(), $failure, self::retryDelayMilliseconds($failure));
        } catch (UrssafException $failure) {
            throw new UnrecoverableMessageHandlingException($failure->getMessage(), 0, $failure);
        }
    }

    /**
     * The failure of a message whose operation is already running (its lock is held): retried later.
     */
    public static function busy(string $resource): RecoverableMessageHandlingException
    {
        return RecoverableFailures::detect()->create(\sprintf('Operation already running (lock "%s" is held).', $resource), null, self::BUSY_RETRY_DELAY_MILLISECONDS);
    }

    private static function retryDelayMilliseconds(Throwable $failure): ?int
    {
        for ($cause = $failure; $cause instanceof Throwable; $cause = $cause->getPrevious()) {
            $seconds = match (true) {
                $cause instanceof UrssafUnavailable => $cause->retryAfterSeconds(),
                $cause instanceof RateLimitExceeded => $cause->retryAfterSeconds(),
                default => null,
            };
            if ($seconds !== null) {
                return $seconds * 1000;
            }
        }

        return null;
    }
}
