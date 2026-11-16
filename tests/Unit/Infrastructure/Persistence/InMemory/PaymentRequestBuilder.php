<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Persistence\InMemory;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

final class PaymentRequestBuilder
{
    private function __construct() {}

    /**
     * Pending request created at the given instant, with its own id, invoice number and tenant.
     */
    public static function pending(string $id, string $tenant, string $invoiceNumber, string $createdAt): PaymentRequest
    {
        $base = Fixtures::validInvoice();
        $invoice = new Invoice(
            $base->customer(),
            $base->billingThirdPartyId(),
            InvoiceNumber::fromString($invoiceNumber),
            $base->date(),
            $base->employmentPeriod(),
            $base->totalInclTax(),
            $base->totalExclTax(),
            $base->lines(),
            null,
        );

        return PaymentRequest::create(
            LocalPaymentRequestId::fromString($id),
            TenantId::fromString($tenant),
            ExternalReference::fromString('ext-' . $id),
            $invoice,
            Instant::fromIso8601($createdAt),
        );
    }

    /**
     * Submitting request (submission started at the given instant). Events are released.
     */
    public static function submitting(string $id, string $tenant, string $invoiceNumber, string $createdAt, string $startedAt): PaymentRequest
    {
        $request = self::pending($id, $tenant, $invoiceNumber, $createdAt);
        $request->startSubmission(Instant::fromIso8601($startedAt));
        $request->releaseEvents();

        return $request;
    }

    /**
     * Submitted request (non final status "10") with the given URSSAF id, never polled. Events are released.
     */
    public static function submitted(string $id, string $tenant, string $invoiceNumber, string $createdAt, string $urssafId): PaymentRequest
    {
        $request = self::submitting($id, $tenant, $invoiceNumber, $createdAt, $createdAt);
        $request->markAccepted(PaymentRequestId::fromString($urssafId), '10', Instant::fromIso8601($createdAt));
        $request->releaseEvents();

        return $request;
    }

    /**
     * Polls the (submitted) request at the given instant. Events are released.
     */
    public static function polledAt(PaymentRequest $request, string $polledAt): PaymentRequest
    {
        $request->applyUrssafStatus('10', null, null, Instant::fromIso8601($polledAt));
        $request->releaseEvents();

        return $request;
    }
}
