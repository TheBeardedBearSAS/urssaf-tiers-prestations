<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequestHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomer;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTiersPrestations\Testing\SimulatedUrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input\DraftFixtures;

/**
 * "gateway.simulated: true": the whole workflow runs against the in-memory URSSAF platform, as in the tests of a host.
 */
final class SimulatedGatewayTest extends TestCase
{
    public function testTheRealGatewayIsUsedByDefault(): void
    {
        $harness = Harness::boot();

        static::assertInstanceOf(UrssafHttpGateway::class, $harness->privateService(UrssafGateway::class, UrssafGateway::class));
        static::assertFalse($harness->has(SimulatedUrssafGateway::class));
    }

    public function testFromTheRegistrationToThePaidPaymentRequestWithoutNetwork(): void
    {
        $harness = Harness::boot(['gateway' => ['simulated' => true], 'workflow' => ['business_timezone' => 'UTC']]);
        $urssaf = $harness->get(SimulatedUrssafGateway::class);
        static::assertSame($urssaf, $harness->privateService(UrssafGateway::class, UrssafGateway::class));
        $urssaf->progressPaymentRequestsThrough(PaymentRequestStatus::Integrated, PaymentRequestStatus::Paid);
        $tenant = TenantId::fromString('tenant-1');
        $reference = ExternalReference::fromString('customer-1');

        $registrationId = $harness->get(RegisterCustomerHandler::class)(new RegisterCustomer($tenant, $reference, CustomerFixtures::officialDraft()));
        static::assertSame(0, $harness->command('urssaf:customers:submit-pending')->execute([]));
        $harness->clock->modify('2022-01-15 10:00:00');   // the customer accepts the pairing before the service
        static::assertSame(0, $harness->command('urssaf:customers:sync-pairing')->execute([]));
        $view = $harness->get(GetCustomerRegistrationHandler::class)(new GetCustomerRegistration($tenant, CustomerSelector::byRegistrationId($registrationId)));
        static::assertSame('registered', $view->state);
        static::assertSame($urssaf->customerIds()[0]->value(), $view->urssafCustomerId);

        $harness->clock->modify('2022-03-01 00:00:00');
        $harness->get(CreatePaymentRequestHandler::class)(new CreatePaymentRequest($tenant, CustomerSelector::byExternalReference($reference), DraftFixtures::official()));
        static::assertSame(0, $harness->command('urssaf:payment-requests:submit-pending')->execute([]));
        static::assertSame(0, $harness->command('urssaf:payment-requests:sync-statuses')->execute([]));

        $views = $harness->get(FindPaymentRequestsHandler::class)(FindPaymentRequests::byInvoiceNumbers($tenant, InvoiceNumber::fromString('2022-AZ-00001')));
        static::assertSame('70', $views[0]->urssaf->status);
        static::assertSame($urssaf->paymentRequestIds()[0]->value(), $views[0]->urssaf->paymentRequestId);
        static::assertSame(1102, $views[0]->urssaf->transferAmountCents);
        static::assertSame('2022-03-01', $views[0]->urssaf->transferValueDate);
        static::assertSame([Operation::RegisterCustomer, Operation::GetCustomerStatus, Operation::SubmitPaymentRequests, Operation::SearchPaymentRequests], $urssaf->calls());
        static::assertSame([], $harness->client->getRequests());
    }
}
