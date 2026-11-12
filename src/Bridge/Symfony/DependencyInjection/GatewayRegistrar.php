<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use DateTimeZone;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TokenStore;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerRegistrar;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestFinder;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSubmitter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\TransmissionStatusReader;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\TransmissionStatusMapper;
use TheBeardedBear\UrssafTiersPrestations\Testing\SimulatedUrssafGateway;

/**
 * The URSSAF HTTP gateway and everything it needs: PSR-18 client, PSR-17 factories, access tokens, JSON client.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class GatewayRegistrar
{
    private const LOGGER_TAG = 'monolog.logger';
    private const LOGGER_CHANNEL = 'urssaf';

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(ServiceIds::SYMFONY_HTTP_CLIENT, HttpClientInterface::class)
            ->factory(HttpClient::class . '::create')
            ->args([['timeout' => $config['http_timeout']]]);
        // Also provides the PSR-17 request and stream factories, whatever the PSR-18 client in use.
        $this->services->set(ServiceIds::PSR18_CLIENT, Psr18Client::class)->args([new Reference(ServiceIds::SYMFONY_HTTP_CLIENT)]);
        $this->services->set(ServiceIds::API_CONFIGURATION, ApiConfiguration::class)->args([
            Environment::from($config['environment']),
            $config['base_uri'],
            $config['application_id'],
            $config['token_safety_margin'],
            ApiConfiguration::DEFAULT_USER_AGENT,
            new Definition(DateTimeZone::class, [$config['pairing_time_zone']]),
        ]);

        $client = new Reference($config['http_client'] ?? ServiceIds::PSR18_CLIENT);
        $factories = new Reference(ServiceIds::PSR18_CLIENT);
        $logger = new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
        $this->services->set(ServiceIds::TOKEN_PROVIDER, OAuth2TokenProvider::class)
            ->args([
                $client,
                $factories,
                $factories,
                new Reference(TokenStore::class),
                new Reference(Clock::class),
                new Reference(ServiceIds::API_CONFIGURATION),
                $logger,
            ])
            ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);
        $this->services->set(ServiceIds::JSON_CLIENT, JsonApiClient::class)
            ->args([
                $client,
                $factories,
                $factories,
                new Reference(ServiceIds::TOKEN_PROVIDER),
                new Reference(RateLimiter::class),
                new Reference(IdGenerator::class),
                new Reference(Clock::class),
                new Reference(ServiceIds::API_CONFIGURATION),
                $logger,
            ])
            ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);

        $this->services->set(UrssafGateway::class, UrssafHttpGateway::class)
            ->args([
                new Reference(CredentialsProvider::class),
                new Reference(ServiceIds::JSON_CLIENT),
                new Definition(TransmissionStatusMapper::class, [new Definition(DateTimeZone::class, [$config['pairing_time_zone']])]),
            ])
            ->arg('$logger', $logger)
            ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);
        foreach ([CustomerRegistrar::class, TransmissionStatusReader::class, PaymentRequestSubmitter::class, PaymentRequestFinder::class] as $port) {
            $this->services->alias($port, UrssafGateway::class);
        }
        if ($config['gateway']['simulated']) {
            $this->services->set(UrssafGateway::class, SimulatedUrssafGateway::class)->args([
                new Reference(Clock::class),
                new Reference(IdGenerator::class),
                new Definition(DateTimeZone::class, [$config['workflow']['business_timezone']]),
            ]);
            $this->services->alias(SimulatedUrssafGateway::class, UrssafGateway::class)->public();
        }
    }
}
