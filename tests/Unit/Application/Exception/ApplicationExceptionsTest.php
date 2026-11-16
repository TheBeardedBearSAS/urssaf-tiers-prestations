<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Exception;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\CustomerAlreadyRegistered;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\DuplicateInvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;

final class ApplicationExceptionsTest extends TestCase
{
    public function testCustomerAlreadyRegistered(): void
    {
        $exception = CustomerAlreadyRegistered::withExternalReference(ExternalReference::fromString('customer-1'), 'reg-1');

        static::assertSame('customer_registration.already_registered', $exception->violationCode());
        static::assertSame(['external_reference' => 'customer-1', 'registration_id' => 'reg-1'], $exception->params());
        static::assertSame('The external reference "customer-1" is already registered with other customer details.', $exception->getMessage());
    }

    public function testDuplicateInvoiceNumber(): void
    {
        $exception = DuplicateInvoiceNumber::of(InvoiceNumber::fromString('INV-1'), 'pr-1');

        static::assertSame('payment_request.duplicate_invoice_number', $exception->violationCode());
        static::assertSame(['invoice_number' => 'INV-1', 'payment_request_id' => 'pr-1'], $exception->params());
        static::assertSame('The invoice number "INV-1" is already used by another payment request.', $exception->getMessage());
    }

    public function testRetryableUrssafFailure(): void
    {
        $previous = UrssafUnavailable::withStatus(Operation::SubmitPaymentRequests, 503, 30);
        $exception = RetryableUrssafFailure::causedBy($previous);

        static::assertSame($previous, $exception->getPrevious());
        static::assertSame(0, $exception->getCode());
        static::assertSame('URSSAF is temporarily unavailable: the submission must be retried later.', $exception->getMessage());
    }
}
