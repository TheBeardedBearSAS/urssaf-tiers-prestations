<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support;

use Doctrine\DBAL\Logging\Middleware;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * Remembers the values bound to the last executed statement that had parameters (plug it through {@see self::middleware()}).
 */
final class StatementParameterRecorder implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<scalar|null> */
    private array $lastBoundValues = [];

    public function middleware(): Middleware
    {
        return new Middleware($this);
    }

    /**
     * @return list<scalar|null>
     */
    public function lastBoundValues(): array
    {
        return $this->lastBoundValues;
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        // @mago-expect analysis:mixed-assignment The PSR-3 context is untyped; it is narrowed right below.
        $parameters = $context['params'] ?? [];
        if (!\is_array($parameters) || $parameters === []) {
            return;
        }

        /** @var array<array-key, scalar|null> $parameters */
        $this->lastBoundValues = array_values($parameters);
    }
}
