<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Sends pending payment requests of one tenant to URSSAF (method 050), by batches of the policy size.
 */
final readonly class SubmitPaymentRequests
{
    /**
     * @param list<LocalPaymentRequestId> $paymentRequestIds
     */
    private function __construct(public TenantId $tenantId, public array $paymentRequestIds) {}

    public static function of(TenantId $tenantId, LocalPaymentRequestId ...$paymentRequestIds): self
    {
        return new self($tenantId, array_values($paymentRequestIds));
    }
}
