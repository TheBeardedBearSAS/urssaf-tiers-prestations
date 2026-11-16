<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerRegistrationView;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\PaymentRequestView;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Rejection;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class ViewsTest extends TestCase
{
    public function testRegisteredCustomer(): void
    {
        $view = CustomerRegistrationView::fromRegistration((new Harness())->registration(3, RegistrationState::Registered));

        static::assertSame('reg-3', $view->registrationId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame('customer-3', $view->externalReference);
        static::assertSame('Durand', $view->birthName);
        static::assertSame('Eric-Antoine', $view->firstNames);
        static::assertSame('registered', $view->state);
        static::assertTrue($view->canReceivePaymentRequests);
        static::assertSame('00000000-0000-4000-8000-000000000003', $view->urssafCustomerId);
        static::assertSame('2022-01-15T10:00:00Z', $view->pairingAcceptedAt);
        static::assertSame([], $view->lastErrors);
        static::assertSame('2022-01-01T00:00:00Z', $view->createdAt);
        static::assertSame('2022-01-02T00:00:00Z', $view->updatedAt);
    }

    public function testRejectedCustomer(): void
    {
        $view = CustomerRegistrationView::fromRegistration((new Harness())->registration(1, RegistrationState::Rejected));

        static::assertSame('rejected', $view->state);
        static::assertFalse($view->canReceivePaymentRequests);
        static::assertNull($view->urssafCustomerId);
        static::assertNull($view->pairingAcceptedAt);
        static::assertSame([['code' => 'ERR_X', 'message' => 'x']], $view->lastErrors);
    }

    public function testPolledPaymentRequest(): void
    {
        $request = (new Harness())->paymentRequest(7, SubmissionState::Submitted);
        $request->applyUrssafStatus(
            '70',
            TransferInfo::of(Money::fromCents(1102), CalendarDate::fromString('2022-03-05')),
            Rejection::of('REGUL_AUTRE', 'comment'),
            Harness::at('2022-03-06T08:00:00Z'),
        );

        $view = PaymentRequestView::fromPaymentRequest($request);

        static::assertSame('pr-7', $view->paymentRequestId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame('invoice-7', $view->externalReference);
        static::assertSame('INV-7', $view->invoiceNumber);
        static::assertSame('2022-02-01', $view->invoiceDate);
        static::assertSame(1102, $view->totalInclTaxCents);
        static::assertSame('submitted', $view->state);
        static::assertSame([], $view->submissionErrors);
        static::assertSame('2022-03-01T00:00:00Z', $view->createdAt);
        static::assertSame('2022-03-06T08:00:00Z', $view->updatedAt);
        static::assertSame('urssaf-7', $view->urssaf->paymentRequestId);
        static::assertSame('70', $view->urssaf->status);
        static::assertSame('Paid', $view->urssaf->statusLabel);
        static::assertSame('2022-03-06T08:00:00Z', $view->urssaf->lastStatusChangeAt);
        static::assertSame('2022-03-06T08:00:00Z', $view->urssaf->lastPolledAt);
        static::assertSame(1102, $view->urssaf->transferAmountCents);
        static::assertSame('2022-03-05', $view->urssaf->transferValueDate);
        static::assertSame('REGUL_AUTRE', $view->urssaf->rejectionCode);
        static::assertSame('comment', $view->urssaf->rejectionComment);
    }

    public function testFailedPaymentRequest(): void
    {
        $request = (new Harness())->paymentRequest(1, SubmissionState::Submitting);
        $request->markFailed([SubmissionError::of('ERR_X', 'message', 'description')], Harness::at(Harness::NOW));

        $view = PaymentRequestView::fromPaymentRequest($request);

        static::assertSame('error', $view->state);
        static::assertSame([['code' => 'ERR_X', 'message' => 'message', 'description' => 'description']], $view->submissionErrors);
        static::assertNull($view->urssaf->paymentRequestId);
        static::assertNull($view->urssaf->status);
        static::assertNull($view->urssaf->statusLabel);
        static::assertNull($view->urssaf->lastStatusChangeAt);
        static::assertNull($view->urssaf->lastPolledAt);
        static::assertNull($view->urssaf->transferAmountCents);
        static::assertNull($view->urssaf->transferValueDate);
        static::assertNull($view->urssaf->rejectionCode);
        static::assertNull($view->urssaf->rejectionComment);
    }
}
