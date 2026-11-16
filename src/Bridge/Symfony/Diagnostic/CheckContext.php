<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Diagnostic;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * What `urssaf:check` checks: the tenants, and whether URSSAF may be called.
 */
final readonly class CheckContext
{
    /**
     * @param non-empty-list<TenantId> $tenants
     */
    private function __construct(public array $tenants, public bool $remoteCallsAllowed) {}

    /**
     * @param non-empty-list<TenantId> $tenants
     */
    public static function withRemoteCalls(array $tenants): self
    {
        return new self($tenants, true);
    }

    /**
     * @param non-empty-list<TenantId> $tenants
     */
    public static function withoutRemoteCalls(array $tenants): self
    {
        return new self($tenants, false);
    }
}
