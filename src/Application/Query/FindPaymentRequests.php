<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Query;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Reads payment requests of a tenant by invoice numbers or by local ids (unknown ones are left out).
 */
final readonly class FindPaymentRequests
{
    /**
     * @param list<InvoiceNumber>         $invoiceNumbers
     * @param list<LocalPaymentRequestId> $paymentRequestIds
     */
    private function __construct(public TenantId $tenantId, public array $invoiceNumbers, public array $paymentRequestIds) {}

    public static function byInvoiceNumbers(TenantId $tenantId, InvoiceNumber ...$invoiceNumbers): self
    {
        return new self($tenantId, array_values($invoiceNumbers), []);
    }

    public static function byIds(TenantId $tenantId, LocalPaymentRequestId ...$paymentRequestIds): self
    {
        return new self($tenantId, [], array_values($paymentRequestIds));
    }
}
