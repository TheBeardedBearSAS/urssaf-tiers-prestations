<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Validator\ConstraintViolationList;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\WorkflowStatusCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Validation\ViolationListConverter;

/**
 * Declares the services of the bundle from its processed configuration. Services are private (the default of the
 * container configurator), except the use case handlers (the entry points of the host application) and the
 * violation list converter (registered when symfony/validator is installed).
 *
 * @phpstan-type Config array{
 *     environment: string,
 *     base_uri: ?string,
 *     application_id: ?string,
 *     token_safety_margin: int,
 *     pairing_time_zone: string,
 *     http_client: ?string,
 *     http_timeout: float,
 *     credentials_provider: ?string,
 *     credentials: array<string, array{client_id: string, client_secret: string}>,
 *     tenants: list<string>,
 *     token_store: array{cache_pool: string, service: ?string},
 *     gateway: array{simulated: bool},
 *     rate_limiter: array{enabled: bool},
 *     lock: array{enabled: bool|string, factory: string, ttl: float},
 *     scheduler: array{enabled: bool, name: string, frequencies: array<string, ?string>},
 *     outbox: array{enabled: bool, service: ?string, sink: string, bus: string, retention: string},
 *     workflow: array{business_timezone: string, settled_max_age: string, stale_submission_after: string, billing_third_party_id: string, batch_size: int, default_page_size: int, max_page_size: int, max_submission_attempts: int, unknown_status_max_age: string, max_polling_age: string},
 *     persistence: array{driver: string, customer_registration_repository: ?string, payment_request_repository: ?string, workflow_statistics: ?string, entity_manager: string},
 *     messenger: array{enabled: bool, bus: string, auto_submit: bool}
 * }
 */
final readonly class ServiceRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        (new InfrastructureRegistrar($this->services))->register($config);
        (new GatewayRegistrar($this->services))->register($config);
        (new PersistenceRegistrar($this->services))->register($config['persistence']);
        (new LockRegistrar($this->services))->register($config['lock']);
        (new ApplicationRegistrar($this->services))->register($config['workflow']);
        $entryPoints = new EntryPointRegistrar($this->services);
        $entryPoints->registerConsole();
        (new DiagnosticRegistrar($this->services))->register($config);
        if (PersistenceRegistrar::hasStatistics($config['persistence']['driver'], $config['persistence']['workflow_statistics'])) {
            $this->services->set(GetWorkflowOverviewHandler::class)->autowire()->public();
            $this->services->set(WorkflowStatusCommand::class)->autowire()->tag('console.command');
        }
        if ($config['messenger']['enabled']) {
            $entryPoints->registerMessenger($config['messenger']);
        }
        if ($config['outbox']['enabled']) {
            (new OutboxRegistrar($this->services))->register($config);
        }
        if ($config['scheduler']['enabled']) {
            $this->services->set(UrssafScheduleProvider::class)
                ->args([$config['outbox']['enabled']
                    ? $config['scheduler']['frequencies']
                    // Without the outbox, its commands do not exist.
                    : [...$config['scheduler']['frequencies'], 'relay_outbox' => null, 'purge_outbox' => null]])
                ->tag('scheduler.schedule_provider', ['name' => $config['scheduler']['name']]);
        }
        if (class_exists(ConstraintViolationList::class)) {
            $this->services->set(ViolationListConverter::class)
                ->args([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->public();
        }
    }

}
