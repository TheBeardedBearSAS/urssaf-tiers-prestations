<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Integration events stored with the state change that produced them (same transaction), then relayed to the host.
 */
interface Outbox
{
    /**
     * Stores the events in the current transaction.
     */
    public function append(IntegrationEvent ...$events): void;

    /**
     * @param positive-int $limit
     *
     * @return list<OutboxEntry> the events not dispatched yet, in recording order
     */
    public function pending(int $limit): array;

    public function markDispatched(OutboxEntry $entry, Instant $at): void;

    /**
     * Keeps the entry pending, with one more failed attempt and the failure message.
     */
    public function markFailed(OutboxEntry $entry, string $error): void;

    /**
     * Deletes the entries dispatched before the instant.
     *
     * @return int the number of deleted entries
     */
    public function purgeDispatchedBefore(Instant $before): int;
}
