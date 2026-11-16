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

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomer;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\CustomerRegistrationSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\RecordingMessageBus;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\RecordingIntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

/**
 * One method per behaviour of the outbox integration.
 *
 * @mago-expect lint:too-many-methods
 */
final class OutboxTest extends TestCase
{
    private const ENABLED = ['outbox' => ['enabled' => true, 'sink' => 'test.sink'], 'gateway' => ['simulated' => true]];

    public function testTheOutboxIsDisabledByDefault(): void
    {
        $container = (new TestKernel(['scheduler' => ['enabled' => true]]))->definitions();

        static::assertFalse($container->hasDefinition(Outbox::class) || $container->hasAlias(Outbox::class));
        static::assertFalse($container->hasDefinition(RelayOutboxHandler::class));
        static::assertSame(['submit_pending' => 'PT5M', 'sync_pairing' => 'PT10M', 'sync_statuses' => 'PT15M', 'reconcile_stale' => 'PT15M', 'relay_outbox' => null, 'purge_outbox' => null], $container->getDefinition(UrssafScheduleProvider::class)->getArgument(0));
    }

    public function testTheEventsOfAUseCaseAreRelayedToTheSink(): void
    {
        $harness = Harness::boot(self::ENABLED);
        $harness->get(RegisterCustomerHandler::class)(new RegisterCustomer(TenantId::fromString('tenant-1'), ExternalReference::fromString('customer-1'), CustomerFixtures::officialDraft()));
        static::assertSame(0, $harness->command('urssaf:customers:submit-pending')->execute([]));

        $relay = $harness->command('urssaf:outbox:relay');
        static::assertSame(0, $relay->execute([]));

        static::assertMatchesRegularExpression('/^\| 1 +\| 0 +\| 0 +\|$/m', $relay->getDisplay());
        $sink = $harness->privateService('test.sink', RecordingIntegrationEventSink::class);
        static::assertCount(1, $sink->deliveredIds());
        static::assertSame([], $harness->privateService(Outbox::class, InMemoryOutbox::class)->pending(10));
    }

    public function testAFailedDeliveryMakesTheRelayFail(): void
    {
        $harness = Harness::boot(self::ENABLED);
        $harness->privateService(Outbox::class, InMemoryOutbox::class)->append(new PaymentRequestSubmitted('e1', '2022-03-01T10:00:00Z', 'tenant-1', 'refused-aggregate', 'ref', 'urssaf-1'));

        $relay = $harness->command('urssaf:outbox:relay');

        static::assertSame(1, $relay->execute(['--limit' => '5']));
        static::assertMatchesRegularExpression('/^\| Dispatched +\| Failed +\| Deferred +\|$/m', $relay->getDisplay());
        static::assertMatchesRegularExpression('/^\| 0 +\| 1 +\| 0 +\|$/m', $relay->getDisplay());
    }

    public function testTheRelayIsSkippedWhileAnotherRunHoldsTheLock(): void
    {
        $harness = Harness::boot(self::ENABLED, false, false, true);
        $lock = $harness->privateService('test.lock_factory', LockFactory::class)->createLock('urssaf_tps.outbox.relay.*');
        $lock->acquire();

        $relay = $harness->command('urssaf:outbox:relay');

        static::assertSame(0, $relay->execute([]));
        static::assertStringContainsString('Already running (lock "urssaf_tps.outbox.relay.*" is held): skipped.', $relay->getDisplay());
    }

    public function testThePurgeUsesTheRetention(): void
    {
        $harness = Harness::boot([...self::ENABLED, 'outbox' => [...self::ENABLED['outbox'], 'retention' => 'P1D']]);
        $definition = (new TestKernel([...self::ENABLED, 'outbox' => [...self::ENABLED['outbox'], 'retention' => 'P1D']]))->definitions()->getDefinition(PurgeOutboxCommand::class);

        $purge = $harness->command('urssaf:outbox:purge');

        static::assertSame(0, $purge->execute([]));
        static::assertStringContainsString('0 dispatched outbox entries deleted.', $purge->getDisplay());
        static::assertEquals(new Definition(DateInterval::class, ['P1D']), $definition->getArgument(1));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, list<mixed>}>
     */
    public static function storages(): iterable
    {
        yield 'doctrine driver' => [['persistence' => ['driver' => 'doctrine', 'entity_manager' => 'urssaf']], DoctrineOutbox::class, [new Reference('doctrine.orm.urssaf_entity_manager')]];
        yield 'in-memory driver' => [[], InMemoryOutbox::class, []];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<mixed>          $arguments
     */
    #[DataProvider('storages')]
    public function testEachDriverHasItsOutbox(array $config, string $class, array $arguments): void
    {
        $definition = (new TestKernel([...$config, 'outbox' => ['enabled' => true]], $config !== []))->definitions()->getDefinition(Outbox::class);

        static::assertSame($class, $definition->getClass());
        static::assertEquals($arguments, $definition->getArguments());
    }

    public function testAServiceOfTheHostCanBeTheOutbox(): void
    {
        $container = (new TestKernel(['persistence' => ['driver' => 'custom', 'customer_registration_repository' => 'test.customers', 'payment_request_repository' => 'test.payment_requests'], 'outbox' => ['enabled' => true, 'service' => 'host.outbox']]))->definitions();

        static::assertSame('host.outbox', (string) $container->getAlias(Outbox::class));
    }

    public function testTheCustomDriverNeedsAnOutboxService(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "custom" persistence driver requires "outbox.service" when the outbox is enabled.');

        Harness::boot(['persistence' => ['driver' => 'custom', 'customer_registration_repository' => 'test.customers', 'payment_request_repository' => 'test.payment_requests'], 'outbox' => ['enabled' => true]]);
    }

    public function testTheSinkIsTheBusTheEventDispatcherOrAService(): void
    {
        $messenger = (new TestKernel(['outbox' => ['enabled' => true, 'bus' => 'event.bus']]))->definitions()->getDefinition(IntegrationEventSink::class);
        $dispatcher = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'event_dispatcher']]))->definitions()->getDefinition(IntegrationEventSink::class);
        $service = (new TestKernel(['outbox' => ['enabled' => true, 'sink' => 'host.sink']]))->definitions()->getAlias(IntegrationEventSink::class);

        static::assertSame(MessengerIntegrationEventSink::class, $messenger->getClass());
        static::assertEquals([new Reference('event.bus')], $messenger->getArguments());
        static::assertSame(Psr14IntegrationEventSink::class, $dispatcher->getClass());
        static::assertEquals([new Reference('event_dispatcher')], $dispatcher->getArguments());
        static::assertSame('host.sink', (string) $service);
    }

    public function testTheOutboxTasksAreScheduledWithTheOutbox(): void
    {
        // @mago-expect analysis:mixed-assignment Definition arguments are untyped: the assertion below narrows it.
        $frequencies = (new TestKernel(['outbox' => ['enabled' => true], 'scheduler' => ['enabled' => true]]))->definitions()->getDefinition(UrssafScheduleProvider::class)->getArgument(0);

        static::assertIsArray($frequencies);
        static::assertSame('PT1M', $frequencies['relay_outbox']);
        static::assertSame('P1D', $frequencies['purge_outbox']);
    }

    public function testTheSinksDeliverTheEvent(): void
    {
        $event = new CustomerRegistrationSubmitted('e1', '2022-03-01T10:00:00Z', 'tenant-1', 'reg-1', 'customer-1', 'urssaf-1');
        $bus = new RecordingMessageBus();
        $dispatcher = new EventDispatcher();
        $received = [];
        $dispatcher->addListener(CustomerRegistrationSubmitted::class, static function (IntegrationEvent $dispatched) use (&$received): void {
            $received[] = $dispatched;
        });

        (new MessengerIntegrationEventSink($bus))->dispatch($event);
        (new Psr14IntegrationEventSink($dispatcher))->dispatch($event);

        static::assertSame([$event], $bus->messages());
        static::assertSame([$event], $received);
    }
}
