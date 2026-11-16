<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequestHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomer;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input\DraftFixtures;

/**
 * The whole life of a customer and of a payment request through the bundle: use case handlers, console commands, the
 * scripted URSSAF platform, with every persistence driver.
 */
final class EndToEndTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function drivers(): iterable
    {
        yield 'in memory' => ['in_memory'];
        yield 'doctrine' => ['doctrine'];
    }

    #[DataProvider('drivers')]
    public function testFromTheRegistrationToThePaidPaymentRequest(string $driver): void
    {
        $harness = Harness::boot(['application_id' => 'my-application', 'persistence' => ['driver' => $driver]], $driver === 'doctrine');
        if ($driver === 'doctrine') {
            $harness->createDatabaseSchema();
        }
        $tenant = TenantId::fromString('tenant-1');
        $reference = ExternalReference::fromString('customer-1');

        // 1. The customer is registered locally.
        $registrationId = $harness->get(RegisterCustomerHandler::class)(new RegisterCustomer($tenant, $reference, CustomerFixtures::officialDraft()));
        $query = new GetCustomerRegistration($tenant, CustomerSelector::byRegistrationId($registrationId));
        $view = $harness->get(GetCustomerRegistrationHandler::class)($query);
        static::assertSame('pending', $view->state);
        static::assertFalse($view->canReceivePaymentRequests);

        // 2. The registration is submitted (010).
        $submit = $harness->command('urssaf:customers:submit-pending');
        static::assertSame(0, $submit->execute([]));
        static::assertStringContainsString($registrationId->value(), $submit->getDisplay());
        static::assertStringContainsString('accepted', $submit->getDisplay());
        $view = $harness->get(GetCustomerRegistrationHandler::class)($query);
        static::assertSame('awaiting_pairing', $view->state);
        static::assertSame(HttpScript::URSSAF_CUSTOMER_ID, $view->urssafCustomerId);

        // 3. The pairing is validated (020).
        $pairing = $harness->command('urssaf:customers:sync-pairing');
        static::assertSame(0, $pairing->execute([]));
        $view = $harness->get(GetCustomerRegistrationHandler::class)($query);
        static::assertSame('registered', $view->state);
        static::assertTrue($view->canReceivePaymentRequests);
        static::assertSame('2022-01-15T10:00:00Z', $view->pairingAcceptedAt);

        // 4. A payment request is created for the customer, then submitted (050).
        $paymentRequestId = $harness->get(CreatePaymentRequestHandler::class)(
            new CreatePaymentRequest($tenant, CustomerSelector::byExternalReference($reference), DraftFixtures::official()),
        );
        $find = FindPaymentRequests::byInvoiceNumbers($tenant, InvoiceNumber::fromString('2022-AZ-00001'));
        $views = $harness->get(FindPaymentRequestsHandler::class)($find);
        static::assertSame('pending', $views[0]->state);

        $submitRequests = $harness->command('urssaf:payment-requests:submit-pending');
        static::assertSame(0, $submitRequests->execute([]));
        static::assertStringContainsString($paymentRequestId->value(), $submitRequests->getDisplay());
        $views = $harness->get(FindPaymentRequestsHandler::class)($find);
        static::assertSame('submitted', $views[0]->state);
        static::assertSame(HttpScript::URSSAF_PAYMENT_REQUEST_ID, $views[0]->urssaf->paymentRequestId);
        static::assertSame('10', $views[0]->urssaf->status);

        // 5. The status is polled (070): paid, with the transfer information.
        $statuses = $harness->command('urssaf:payment-requests:sync-statuses');
        static::assertSame(0, $statuses->execute([]));
        $views = $harness->get(FindPaymentRequestsHandler::class)($find);
        static::assertSame('70', $views[0]->urssaf->status);
        static::assertSame(827, $views[0]->urssaf->transferAmountCents);
        static::assertSame('2022-02-20', $views[0]->urssaf->transferValueDate);
        static::assertSame('2022-03-01T00:00:00Z', $views[0]->urssaf->lastPolledAt);

        // The platform was called once per method with the configured headers. The access token is requested for each call:
        // the cache pool expires entries on the real time while the test clock is in 2022.
        static::assertSame(4, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
        foreach ([HttpScript::ROUTE_REGISTER, HttpScript::ROUTE_PAIRING, HttpScript::ROUTE_SUBMIT, HttpScript::ROUTE_SEARCH] as $route) {
            static::assertSame(1, $harness->urssaf->calls($route), $route);
        }
        $apiRequest = $harness->client->getRequests()[1];
        static::assertSame('Bearer fictitious-access-token', $apiRequest->getHeaderLine('Authorization'));
        static::assertSame('my-application', $apiRequest->getHeaderLine('recouv.application.id'));
        static::assertSame('api-edi.urssaf.fr', $apiRequest->getUri()->getHost());
    }
}
