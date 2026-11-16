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

use ReflectionClass;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Throwable;

/**
 * Builds {@see RecoverableMessageHandlingException} instances, passing the retry delay only when the installed
 * symfony/messenger supports it (the exception only carries a delay, and its constructor only accepts one, from 7.2; older
 * versions simply retry with the retry strategy of the transport).
 */
final readonly class RecoverableFailures
{
    private function __construct(private bool $supportsRetryDelay) {}

    /**
     * Detects the capability of the installed symfony/messenger.
     */
    public static function detect(): self
    {
        return new self((new ReflectionClass(RecoverableMessageHandlingException::class))->hasMethod('getRetryDelay'));
    }

    /**
     * Explicit capability, for hosts or tests that must not depend on the installed version.
     */
    public static function withRetryDelaySupport(bool $supportsRetryDelay): self
    {
        return new self($supportsRetryDelay);
    }

    public function supportsRetryDelay(): bool
    {
        return $this->supportsRetryDelay;
    }

    public function create(string $message, ?Throwable $previous, ?int $retryDelayMilliseconds): RecoverableMessageHandlingException
    {
        if (!$this->supportsRetryDelay) {
            return new RecoverableMessageHandlingException($message, 0, $previous);
        }

        return new RecoverableMessageHandlingException($message, 0, $previous, $retryDelayMilliseconds);
    }
}
