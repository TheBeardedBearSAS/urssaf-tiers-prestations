<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Contract;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Clock\SystemClock;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Credentials\InMemoryCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafHttpGateway;

/**
 * Wires the real HTTP gateway against the URSSAF sandbox ("environnement Editeur") from environment variables:
 *
 * - URSSAF_SANDBOX_CLIENT_ID / URSSAF_SANDBOX_CLIENT_SECRET (required, otherwise the suite is skipped);
 * - URSSAF_SANDBOX_BASE_URI (optional host override).
 */
final readonly class SandboxGateway
{
    public const TENANT = 'contract-tests';

    private function __construct(
        public UrssafHttpGateway $gateway,
        public OAuth2TokenProvider $tokenProvider,
        public Credentials $credentials,
        public TenantId $tenantId,
    ) {}

    /**
     * Null when the required environment variables are missing.
     */
    public static function fromEnvironment(): ?self
    {
        $clientId = self::env('URSSAF_SANDBOX_CLIENT_ID');
        $clientSecret = self::env('URSSAF_SANDBOX_CLIENT_SECRET');
        if ($clientId === null || $clientSecret === null) {
            return null;
        }

        $tenantId = TenantId::fromString(self::TENANT);
        $credentials = Credentials::of($clientId, $clientSecret);
        $configuration = new ApiConfiguration(
            Environment::Sandbox,
            self::env('URSSAF_SANDBOX_BASE_URI'),
            null,
        );

        $factory = new Psr17Factory();
        $httpClient = new Psr18Client(HttpClient::create(['timeout' => 30]), $factory, $factory);
        $clock = new SystemClock(new NativeClock());
        $tokenProvider = new OAuth2TokenProvider(
            $httpClient,
            $factory,
            $factory,
            new InMemoryTokenStore(),
            $clock,
            $configuration,
        );
        $client = new JsonApiClient(
            $httpClient,
            $factory,
            $factory,
            $tokenProvider,
            new NoopRateLimiter(),
            new RandomUuidGenerator(new SecureRandomBytes()),
            $clock,
            $configuration,
        );

        return new self(
            new UrssafHttpGateway(
                new InMemoryCredentialsProvider([$tenantId->value() => $credentials]),
                $client,
            ),
            $tokenProvider,
            $credentials,
            $tenantId,
        );
    }

    public static function env(string $name): ?string
    {
        $value = getenv($name);

        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
