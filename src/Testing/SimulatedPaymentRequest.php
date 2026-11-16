<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Testing;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * A payment request accepted by the {@see SimulatedUrssafGateway}: the submission returns the first status of its
 * sequence, each search the next one, the last one is repeated.
 *
 * @internal
 */
final class SimulatedPaymentRequest
{
    /**
     * @param array<PaymentRequestStatus> $next
     */
    public function __construct(
        public readonly PaymentRequestId $id,
        public readonly TenantId $tenantId,
        public readonly string $invoiceNumber,
        public readonly CalendarDate $invoiceDate,
        public readonly Money $total,
        private PaymentRequestStatus $current,
        private array $next,
    ) {}

    public function current(): PaymentRequestStatus
    {
        return $this->current;
    }

    /**
     * Moves to the next status (if any) and returns it.
     */
    public function poll(): PaymentRequestStatus
    {
        $this->current = array_shift($this->next) ?? $this->current;

        return $this->current;
    }

    public function force(PaymentRequestStatus $status): void
    {
        $this->current = $status;
        $this->next = [];
    }
}
