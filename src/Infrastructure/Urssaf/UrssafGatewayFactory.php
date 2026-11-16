<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf;

use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TokenStore;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\SlidingWindowRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\CustomerRegistrationMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\ErrorPayloadParser;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestSearchMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestSnapshotMapper;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\TransmissionStatusMapper;

/**
 * Public entry point to build the URSSAF HTTP gateway outside a framework bridge:
 *
 *     $gateway = UrssafGatewayFactory::createWith(
 *         new HttpTransport($psr18Client, $psr17RequestFactory, $psr17StreamFactory),
 *         $credentialsProvider,
 *         $tokenStore,
 *         ApiConfiguration::forEnvironment(Environment::Production),
 *         GatewayOptions::defaults()->withLogger($logger),
 *     );
 *
 * {@see self::create()} takes the same arguments without the options.
 *
 * The OAuth2 token provider and the JSON client it wires are internal.
 */
final class UrssafGatewayFactory
{
    /**
     * Gateway with the default options ({@see GatewayOptions::defaults()}).
     */
    public static function create(
        HttpTransport $transport,
        CredentialsProvider $credentials,
        TokenStore $tokens,
        ApiConfiguration $configuration,
    ): UrssafHttpGateway {
        return self::createWith($transport, $credentials, $tokens, $configuration, GatewayOptions::defaults());
    }

    public static function createWith(
        HttpTransport $transport,
        CredentialsProvider $credentials,
        TokenStore $tokens,
        ApiConfiguration $configuration,
        GatewayOptions $options,
    ): UrssafHttpGateway {
        $clock = $options->clock() ?? new UtcSystemClock();
        $logger = $options->logger() ?? new NullLogger();
        $tokenProvider = new OAuth2TokenProvider(
            $transport->client(),
            $transport->requestFactory(),
            $transport->streamFactory(),
            $tokens,
            $clock,
            $configuration,
            $logger,
        );
        $client = new JsonApiClient(
            $transport->client(),
            $transport->requestFactory(),
            $transport->streamFactory(),
            $tokenProvider,
            $options->rateLimiter() ?? new SlidingWindowRateLimiter($clock),
            $options->idGenerator() ?? new RandomUuidGenerator(new SecureRandomBytes()),
            $clock,
            $configuration,
            $logger,
        );

        return new UrssafHttpGateway(
            $credentials,
            $client,
            new TransmissionStatusMapper($configuration->pairingTimeZone()),
            new PaymentRequestSearchMapper(),
            new PaymentRequestSnapshotMapper(),
            new ErrorPayloadParser(),
            new CustomerRegistrationMapper(),
            new PaymentRequestMapper(),
            $logger,
        );
    }
}
