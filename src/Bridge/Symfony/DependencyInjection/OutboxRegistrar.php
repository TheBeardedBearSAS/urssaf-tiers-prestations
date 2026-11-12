<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use DateInterval;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineOutbox;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\PurgeOutboxCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\RelayOutboxCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\Psr14IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Outbox\InMemoryOutbox;

/**
 * The outbox (written by the UnitOfWork, which autowires it), its sink, relay and purge.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class OutboxRegistrar
{
    public const SINK_MESSENGER = 'messenger';
    public const SINK_EVENT_DISPATCHER = 'event_dispatcher';

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $outbox = $config['outbox'];
        $this->registerOutbox($outbox['service'], $config['persistence']['driver'], $config['persistence']['entity_manager']);
        $this->registerSink($outbox['sink'], $outbox['bus']);
        $this->services->set(RelayOutboxHandler::class)->autowire()->public();
        $this->services->set(PurgeOutboxHandler::class)->autowire()->public();
        $this->services->set(RelayOutboxCommand::class)->autowire()->tag('console.command');
        $this->services->set(PurgeOutboxCommand::class)
            ->args([new Reference(PurgeOutboxHandler::class), new Definition(DateInterval::class, [$outbox['retention']])])
            ->tag('console.command');
    }

    private function registerOutbox(?string $service, string $driver, string $entityManager): void
    {
        if ($service !== null) {
            $this->services->alias(Outbox::class, $service);

            return;
        }
        if ($driver === BundleConfiguration::DRIVER_DOCTRINE) {
            $this->services->set(Outbox::class, DoctrineOutbox::class)->args([new Reference(\sprintf('doctrine.orm.%s_entity_manager', $entityManager))]);

            return;
        }
        $this->services->set(Outbox::class, InMemoryOutbox::class);
    }

    private function registerSink(string $sink, string $bus): void
    {
        if ($sink === self::SINK_MESSENGER) {
            $this->services->set(IntegrationEventSink::class, MessengerIntegrationEventSink::class)->args([new Reference($bus)]);

            return;
        }
        if ($sink === self::SINK_EVENT_DISPATCHER) {
            $this->services->set(IntegrationEventSink::class, Psr14IntegrationEventSink::class)->args([new Reference('event_dispatcher')]);

            return;
        }
        $this->services->alias(IntegrationEventSink::class, $sink);
    }
}
