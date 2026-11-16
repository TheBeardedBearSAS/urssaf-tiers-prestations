<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestCreated;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification\ViolationAssertions;

final class PaymentRequestCreationTest extends TestCase
{
    public function testCreate(): void
    {
        $request = Fixtures::pendingRequest();
        $invoice = $request->invoice();

        static::assertSame('local-1', $request->id()->value());
        static::assertSame('tenant-1', $request->tenantId()->value());
        static::assertSame('invoice-42', $request->externalReference()->value());
        static::assertSame(SubmissionState::Pending, $request->state());
        static::assertSame([], $request->submissionErrors());
        static::assertSame([], $request->tracking()->history());
        static::assertSame(Fixtures::NOW, $request->timeline()->createdAt()->toIso8601());
        static::assertSame(Fixtures::NOW, $request->timeline()->updatedAt()->toIso8601());
        static::assertSame($invoice->customer()->id(), $request->urssafCustomerId());
        static::assertSame($invoice->customer()->birthDate(), $request->customerBirthDate());
        static::assertSame($invoice->billingThirdPartyId(), $request->billingThirdPartyId());
        static::assertSame($invoice->number(), $request->invoiceNumber());
        static::assertSame($invoice->date(), $request->invoiceDate());
        static::assertSame($invoice->employmentPeriod(), $request->employmentPeriod());
        static::assertSame($invoice->totalInclTax(), $request->totalInclTax());
        static::assertSame($invoice->totalExclTax(), $request->totalExclTax());
        static::assertSame($invoice->lines(), $request->lines());
        static::assertNull($request->deposit());
    }

    public function testNothingIsKnownAtUrssafYet(): void
    {
        $request = Fixtures::pendingRequest();

        static::assertNull($request->paymentRequestId());
        static::assertNull($request->urssafStatus());
        static::assertNull($request->rawUrssafStatus());
        static::assertNull($request->lastStatusChangeAt());
        static::assertNull($request->lastPolledAt());
    }

    public function testCreateRecordsAnEvent(): void
    {
        $request = Fixtures::pendingRequest();
        $events = $request->releaseEvents();

        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(PaymentRequestCreated::class, $event);
        static::assertSame($request->id(), $event->paymentRequestId());
        static::assertSame($request->tenantId(), $event->tenantId());
        static::assertSame($request->invoiceNumber(), $event->invoiceNumber());
        static::assertSame(Fixtures::NOW, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
        static::assertSame([], $request->releaseEvents());
    }

    public function testCreateRefusesAnInconsistentInvoice(): void
    {
        $line = Fixtures::line('1', '5.51', '5.51', '1', '0');

        try {
            PaymentRequest::create(
                LocalPaymentRequestId::fromString('local-1'),
                TenantId::fromString('tenant-1'),
                ExternalReference::fromString('invoice-42'),
                Fixtures::invoice(ServiceLines::of($line), '5.51', '5.01', null),
                Fixtures::at(Fixtures::NOW),
            );
            static::fail('Expected exception');
        } catch (ValidationFailed $exception) {
            static::assertSame(
                ['error lines[0].amounts.vatAmount line.vat_mismatch', 'warning totalExclTax invoice.total_excl_tax_mismatch'],
                ViolationAssertions::summary($exception->result()),
            );
        }
    }

    public function testCreateAcceptsWarnings(): void
    {
        $line = Fixtures::line('1', '5.51', '5.51', '5.01', '0.50');
        $request = PaymentRequest::create(
            LocalPaymentRequestId::fromString('local-1'),
            TenantId::fromString('tenant-1'),
            ExternalReference::fromString('invoice-42'),
            Fixtures::invoice(ServiceLines::of($line), '5.51', '1', null),
            Fixtures::at(Fixtures::NOW),
        );

        static::assertSame(SubmissionState::Pending, $request->state());
    }
}
