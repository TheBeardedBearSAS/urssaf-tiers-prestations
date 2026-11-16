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

use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairing;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairingHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantCustomerPairingMessage;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

final readonly class SyncTenantCustomerPairingMessageHandler
{
    public function __construct(private SyncCustomerPairingHandler $sync, private OperationLocks $locks) {}

    public function __invoke(SyncTenantCustomerPairingMessage $message): void
    {
        $this->locks->exclusively(
            'customers.sync-pairing',
            $message->tenantId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->sync)(new SyncCustomerPairing(TenantId::fromString($message->tenantId), null))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
