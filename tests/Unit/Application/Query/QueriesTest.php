<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\PaymentRequestView;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RegistrationLocator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class QueriesTest extends TestCase
{
    public function testGetsACustomerRegistration(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Registered);
        $handler = new GetCustomerRegistrationHandler(new RegistrationLocator($harness->registrations));

        $view = $handler(new GetCustomerRegistration(Harness::tenant(), CustomerSelector::byExternalReference(ExternalReference::fromString('customer-1'))));

        static::assertSame('reg-1', $view->registrationId);
        static::assertSame('registered', $view->state);
        $this->expectException(RecordNotFound::class);
        $handler(new GetCustomerRegistration(Harness::tenant(Harness::OTHER_TENANT), CustomerSelector::byExternalReference(ExternalReference::fromString('customer-1'))));
    }

    public function testFindsPaymentRequestsByInvoiceNumber(): void
    {
        $harness = new Harness();
        $harness->paymentRequest(1, SubmissionState::Pending);
        $harness->paymentRequest(2, SubmissionState::Submitted);
        $harness->paymentRequest(3, SubmissionState::Pending, Harness::OTHER_TENANT);
        $handler = new FindPaymentRequestsHandler($harness->paymentRequests);

        $views = $handler(FindPaymentRequests::byInvoiceNumbers(
            Harness::tenant(),
            InvoiceNumber::fromString('INV-2'),
            InvoiceNumber::fromString('INV-3'),
            InvoiceNumber::fromString('INV-1'),
        ));

        static::assertSame(['pr-2', 'pr-1'], self::ids($views));
        static::assertSame('submitted', $views[0]->state);
    }

    public function testFindsPaymentRequestsById(): void
    {
        $harness = new Harness();
        $harness->paymentRequest(1, SubmissionState::Pending);
        $harness->paymentRequest(2, SubmissionState::Pending);
        $harness->paymentRequest(3, SubmissionState::Pending, Harness::OTHER_TENANT);
        $handler = new FindPaymentRequestsHandler($harness->paymentRequests);

        $views = $handler(FindPaymentRequests::byIds(
            Harness::tenant(),
            LocalPaymentRequestId::fromString('pr-2'),
            LocalPaymentRequestId::fromString('pr-9'),
            LocalPaymentRequestId::fromString('pr-3'),
            LocalPaymentRequestId::fromString('pr-1'),
        ));

        static::assertSame(['pr-2', 'pr-1'], self::ids($views));
    }

    public function testQueryShapes(): void
    {
        $number = InvoiceNumber::fromString('INV-1');
        $id = LocalPaymentRequestId::fromString('pr-1');
        $byNumbers = FindPaymentRequests::byInvoiceNumbers(Harness::tenant(), ...['number' => $number]);
        $byIds = FindPaymentRequests::byIds(Harness::tenant(), ...['id' => $id]);

        static::assertSame('tenant-1', $byNumbers->tenantId->value());
        static::assertSame([$number], $byNumbers->invoiceNumbers);
        static::assertSame([], $byNumbers->paymentRequestIds);
        static::assertSame([], $byIds->invoiceNumbers);
        static::assertSame([$id], $byIds->paymentRequestIds);
    }

    /**
     * @param list<PaymentRequestView> $views
     *
     * @return list<string>
     */
    private static function ids(array $views): array
    {
        return array_map(static fn(PaymentRequestView $view): string => $view->paymentRequestId, $views);
    }
}
