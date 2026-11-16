<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TokenStore;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\Psr14EventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Text\AsciiNameNormalizer;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\Psr6TokenStore;

/**
 * Generic infrastructure: clock, identifiers, events, credentials, access token cache, rate limiter.
 *
 * @phpstan-import-type Config from ServiceRegistrar
 */
final readonly class InfrastructureRegistrar
{
    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param Config $config
     */
    public function register(array $config): void
    {
        $this->services->set(Clock::class, Clock::class)
            ->factory(ClockFactory::class . '::create')
            ->args([new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $this->services->set(IdGenerator::class, RandomUuidGenerator::class)->args([new Definition(SecureRandomBytes::class)]);
        $this->services->set(EventPublisher::class, Psr14EventPublisher::class)->args([new Reference('event_dispatcher')]);
        $this->services->set(AsciiNameNormalizer::class)->args([\extension_loaded('intl')]);
        $this->registerCredentials($config['credentials_provider'], $config['credentials']);
        $this->registerTokenStore($config['token_store']);
        $this->registerRateLimiter($config['rate_limiter']);
    }

    /**
     * @param array<string, array{client_id: string, client_secret: string}> $credentials
     */
    private function registerCredentials(?string $provider, array $credentials): void
    {
        if ($provider !== null) {
            $this->services->alias(CredentialsProvider::class, $provider);

            return;
        }
        $definitions = [];
        foreach ($credentials as $tenantId => $tenant) {
            $definitions[$tenantId] = (new Definition(Credentials::class, [$tenant['client_id'], $tenant['client_secret']]))
                ->setFactory([Credentials::class, 'of']);
        }
        $this->services->set(CredentialsProvider::class, InMemoryCredentialsProvider::class)->args([$definitions]);
    }

    /**
     * @param array{cache_pool: string, service: ?string} $tokenStore
     */
    private function registerTokenStore(array $tokenStore): void
    {
        if ($tokenStore['service'] !== null) {
            $this->services->alias(TokenStore::class, $tokenStore['service']);

            return;
        }
        $this->services->set(TokenStore::class, Psr6TokenStore::class)->args([new Reference($tokenStore['cache_pool'])]);
    }

    /**
     * @param array{enabled: bool} $rateLimiter
     */
    private function registerRateLimiter(array $rateLimiter): void
    {
        if ($rateLimiter['enabled']) {
            $this->services->set(RateLimiter::class, SlidingWindowRateLimiter::class)->args([new Reference(Clock::class)]);

            return;
        }
        $this->services->set(RateLimiter::class, NoopRateLimiter::class);
    }
}
