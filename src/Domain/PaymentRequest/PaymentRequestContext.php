<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Everything a payment request draft is validated against, provided by the application layer:
 * - the tenant and the registered, paired customer (URSSAF id + registered birth date);
 * - the billing third party id (arbitrary value, host configuration; official example "menage.fr");
 * - the pairing acceptance instant (lower bound of the employment period) and "now" (upper bound);
 * - the business time zone in which the same-month rule of employment periods is checked (with UTC).
 */
final readonly class PaymentRequestContext
{
    public function __construct(
        private TenantId $tenantId,
        private InvoicedCustomer $customer,
        private BillingThirdPartyId $billingThirdPartyId,
        private Instant $pairingAcceptedAt,
        private Instant $now,
        private DateTimeZone $businessTimeZone,
    ) {}

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function customer(): InvoicedCustomer
    {
        return $this->customer;
    }

    public function billingThirdPartyId(): BillingThirdPartyId
    {
        return $this->billingThirdPartyId;
    }

    public function pairingAcceptedAt(): Instant
    {
        return $this->pairingAcceptedAt;
    }

    public function now(): Instant
    {
        return $this->now;
    }

    public function businessTimeZone(): DateTimeZone
    {
        return $this->businessTimeZone;
    }
}
