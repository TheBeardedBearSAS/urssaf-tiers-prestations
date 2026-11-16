<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Standalone;

use DateInterval;
use DateTimeZone;
use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleRegistrations;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleSubmissions;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomer;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RequeueAfterInvalidCredentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPendingPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllCustomerPairings;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllPaymentRequestStatuses;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairing;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatuses;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\ApplicationRegistrar;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestSubmitted as PaymentRequestSubmittedEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\Psr14EventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTiersPrestations\Standalone\UrssafTiersPrestations;
use TheBeardedBear\UrssafTiersPrestations\Standalone\UrssafTiersPrestationsBuilder;
use TheBeardedBear\UrssafTiersPrestations\Testing\SimulatedUrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support\TestDatabase;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\ThrowingListener;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\SequentialIdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input\DraftFixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;

final class UrssafTiersPrestationsBuilderTest extends TestCase
{
    private const ID_PREFIX = '00000000-0000-4000-8000-0000000000';

    public function testEveryUseCaseOfTheBundleIsAvailable(): void
    {
        // @mago-expect analysis:mixed-assignment Reflected constants are untyped: the assertion below narrows it.
        $handlers = (new ReflectionClass(ApplicationRegistrar::class))->getConstant('HANDLERS');
        static::assertIsArray($handlers);
        $available = [];
        foreach ((new ReflectionClass(UrssafTiersPrestations::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();
            if ($method->isConstructor() || !$type instanceof ReflectionNamedType || !str_ends_with($type->getName(), 'Handler')) {
                continue;
            }
            $available[] = $type->getName();
        }
        $expected = [...$handlers, GetWorkflowOverviewHandler::class, RelayOutboxHandler::class, PurgeOutboxHandler::class];
        sort($available);
        sort($expected);

        static::assertSame($expected, $available);
    }

    public function testTheWholeWorkflowRunsWithTheDefaults(): void
    {
        $psrClock = new MockClock('2022-01-15 10:00:00 UTC');
        $clock = new SystemClock($psrClock);
        $urssaf = new SimulatedUrssafGateway($clock, new SequentialIdGenerator(self::ID_PREFIX . '0'));
        $urssaf->progressPaymentRequestsThrough(PaymentRequestStatus::Integrated, PaymentRequestStatus::Paid);
        $outbox = new InMemoryOutbox();
        $sink = new RecordingIntegrationEventSink();
        $package = self::builder()
            ->withGateway($urssaf)
            ->withClock($clock)
            ->withIdGenerator(new SequentialIdGenerator(self::ID_PREFIX . '1'))
            ->withOutbox($outbox, $sink)
            ->build();
        $tenant = TenantId::fromString('tenant-1');
        $reference = ExternalReference::fromString('customer-1');

        $registrationId = $package->registerCustomer()(new RegisterCustomer($tenant, $reference, CustomerFixtures::officialDraft()));
        $package->submitCustomerRegistration()(new SubmitCustomerRegistration($registrationId));
        $package->syncCustomerPairing()(new SyncCustomerPairing($tenant, null));
        $psrClock->modify('2022-03-01 00:00:00 UTC');
        $package->createPaymentRequest()(new CreatePaymentRequest($tenant, CustomerSelector::byExternalReference($reference), DraftFixtures::official()));
        $package->submitPendingPaymentRequests()(new SubmitPendingPaymentRequests($tenant, null));
        $package->syncPaymentRequestStatuses()(new SyncPaymentRequestStatuses($tenant, null));

        static::assertSame('registered', $package->getCustomerRegistration()(new GetCustomerRegistration($tenant, CustomerSelector::byRegistrationId($registrationId)))->state);
        $views = $package->findPaymentRequests()(FindPaymentRequests::byInvoiceNumbers($tenant, InvoiceNumber::fromString('2022-AZ-00001')));
        static::assertSame('70', $views[0]->urssaf->status);
        static::assertSame(1, $package->getWorkflowOverview()(GetWorkflowOverview::allTenants())[0]->registrations['registered']);
        // Registration submitted, pairing changed, payment request submitted, status changed (70).
        static::assertSame(4, $package->relayOutbox()(new RelayOutbox(null))->dispatched);
        static::assertCount(4, $sink->deliveredIds());
        $psrClock->modify('2022-04-15 00:00:00 UTC');
        static::assertSame(4, $package->purgeOutbox()(new PurgeOutbox(new DateInterval('P30D'))));
        static::assertSame($urssaf, $package->gateway());
    }

    public function testTheBatchUseCasesRunOnAnEmptyStore(): void
    {
        $urssaf = new SimulatedUrssafGateway(new SystemClock(new MockClock()), new SequentialIdGenerator());
        $package = self::builder()->withGateway($urssaf)->build();
        $tenant = TenantId::fromString('tenant-1');

        static::assertSame([], $package->syncAllCustomerPairings()(new SyncAllCustomerPairings(null))->items());
        static::assertSame([], $package->reconcileStaleRegistrations()(new ReconcileStaleRegistrations(null))->items());
        static::assertSame([], $package->syncAllPaymentRequestStatuses()(new SyncAllPaymentRequestStatuses(null))->items());
        static::assertSame([], $package->reconcileStaleSubmissions()(new ReconcileStaleSubmissions(null))->items());
        static::assertSame(0, $package->requeueAfterInvalidCredentials()(new RequeueAfterInvalidCredentials($tenant, null))->registrations);
        static::assertSame(['not_found'], array_map(
            static fn(BatchItem $item): string => $item->outcome->value,
            $package->submitPaymentRequests()(SubmitPaymentRequests::of($tenant, LocalPaymentRequestId::fromString('local-9')))->items(),
        ));
        static::assertSame([], $urssaf->calls());
    }

    public function testThePolicyBoundsTheBatches(): void
    {
        $outbox = new InMemoryOutbox();
        $outbox->append(
            new PaymentRequestSubmittedEvent('e1', '2022-03-01T10:00:00Z', 'tenant-1', 'a', 'ref-a', 'urssaf-a'),
            new PaymentRequestSubmittedEvent('e2', '2022-03-01T10:00:00Z', 'tenant-1', 'b', 'ref-b', 'urssaf-b'),
        );
        $policy = new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P90D'), new DateInterval('PT15M'), 'menage.fr', 10, 1, 1);

        $report = self::builder()->withPolicy($policy)->withOutbox($outbox, new RecordingIntegrationEventSink())->build()->relayOutbox()(new RelayOutbox(null));

        static::assertSame(1, $report->dispatched);
    }

    public function testTheHttpGatewayIsBuiltFromTheTransportAndItsOptions(): void
    {
        $limited = self::builder()->withRateLimiter(new RejectingRateLimiter())->withTokenStore(new InMemoryTokenStore())->build();

        static::assertInstanceOf(UrssafHttpGateway::class, self::builder()->build()->gateway());
        $this->expectException(RateLimitExceeded::class);
        $limited->gateway()->search(TenantId::fromString('tenant-1'), PaymentRequestSearch::byPaymentRequestIds(PaymentRequestId::fromString('urssaf-1')));
    }

    public function testTheLoggerReceivesTheEventPublicationFailures(): void
    {
        $logger = new CapturingLogger();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CustomerRegistrationRequested::class, new ThrowingListener());
        $package = self::builder()->withLogger($logger)->withEventPublisher(new Psr14EventPublisher($dispatcher))->build();

        $package->registerCustomer()(new RegisterCustomer(TenantId::fromString('tenant-1'), ExternalReference::fromString('customer-1'), CustomerFixtures::officialDraft()));

        static::assertNotSame([], $logger->records());
    }

    public function testTheDoctrinePersistenceIsUsedWithItsStatistics(): void
    {
        $entityManager = TestDatabase::entityManager();
        $package = self::builder()->withDoctrine($entityManager)->build();

        $package->registerCustomer()(new RegisterCustomer(TenantId::fromString('tenant-1'), ExternalReference::fromString('customer-1'), CustomerFixtures::officialDraft()));

        $count = $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM urssaf_tps_customer_registrations');
        static::assertIsNumeric($count);
        static::assertSame(1, (int) $count);
        static::assertSame(1, $package->getWorkflowOverview()(GetWorkflowOverview::allTenants())[0]->registrations['pending']);
    }

    public function testTheOverviewNeedsStatisticsAndTheRelayASink(): void
    {
        $package = self::builder()->withRepositories(
            new InMemoryCustomerRegistrationRepository(),
            new InMemoryPaymentRequestRepository(),
            new ImmediateTransactionManager(),
            null,
        )->build();

        try {
            $package->getWorkflowOverview();
            static::fail('The overview should not be available.');
        } catch (LogicException $failure) {
            static::assertSame('No WorkflowStatistics: give one with the repositories (withRepositories()).', $failure->getMessage());
        }
        try {
            $package->relayOutbox();
            static::fail('The relay should not be available.');
        } catch (LogicException $failure) {
            static::assertSame('No IntegrationEventSink: give the outbox and its sink (withOutbox()).', $failure->getMessage());
        }
    }

    public function testABuilderIsNeverModified(): void
    {
        $builder = self::builder();
        $simulated = $builder->withGateway(new SimulatedUrssafGateway(new SystemClock(new MockClock()), new SequentialIdGenerator()));

        static::assertInstanceOf(UrssafHttpGateway::class, $builder->build()->gateway());
        static::assertInstanceOf(SimulatedUrssafGateway::class, $simulated->build()->gateway());
    }

    private static function builder(): UrssafTiersPrestationsBuilder
    {
        $factory = new Psr17Factory();

        return UrssafTiersPrestationsBuilder::create(
            new HttpTransport(new Client(), $factory, $factory),
            new InMemoryCredentialsProvider(['tenant-1' => Credentials::of('client-1', 'secret-1')]),
            new ApiConfiguration(Environment::Sandbox, null, 'my-application'),
        );
    }
}
