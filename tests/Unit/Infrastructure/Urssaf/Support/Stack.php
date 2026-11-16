<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support;

use Http\Mock\Client;
use LogicException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\OAuth2TokenProvider;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafHttpGateway;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\RecordingRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;

/**
 * Wiring of the Urssaf infrastructure on top of a PSR-18 mock client and in-memory fakes.
 */
final readonly class Stack
{
    public Client $http;
    public InMemoryTokenStore $tokens;
    public RecordingRateLimiter $rateLimiter;
    public SteppingClock $clock;
    public CapturingLogger $logger;
    public StaticCredentialsProvider $credentials;
    private Psr17Factory $factory;
    private SequenceIdGenerator $ids;

    public function __construct(public ApiConfiguration $configuration, int $clockStepMicroseconds)
    {
        $this->http = new Client();
        $this->tokens = new InMemoryTokenStore();
        $this->rateLimiter = new RecordingRateLimiter();
        $this->clock = new SteppingClock($clockStepMicroseconds);
        $this->logger = new CapturingLogger();
        $this->credentials = new StaticCredentialsProvider();
        $this->factory = new Psr17Factory();
        $this->ids = new SequenceIdGenerator();
    }

    /**
     * Fetches and caches a token now, so that a later failure queued on the mock client hits the API call itself.
     */
    public function warmUpToken(): void
    {
        $this->respondToken();
        $this->tokenProvider()->accessToken(
            $this->credentials->forTenant(Stacks::tenant()),
            Operation::GetCustomerStatus,
        );
        $this->logger->clear();
    }

    public function tokenProvider(): OAuth2TokenProvider
    {
        return new OAuth2TokenProvider(
            $this->http,
            $this->factory,
            $this->factory,
            $this->tokens,
            $this->clock,
            $this->configuration,
            $this->logger,
        );
    }

    public function jsonClient(): JsonApiClient
    {
        return new JsonApiClient(
            $this->http,
            $this->factory,
            $this->factory,
            $this->tokenProvider(),
            $this->rateLimiter,
            $this->ids,
            $this->clock,
            $this->configuration,
            $this->logger,
        );
    }

    /**
     * The gateway built by the public factory, on top of the same mock client and fakes.
     */
    public function gateway(): UrssafHttpGateway
    {
        return UrssafGatewayFactory::createWith(
            new HttpTransport($this->http, $this->factory, $this->factory),
            $this->credentials,
            $this->tokens,
            $this->configuration,
            GatewayOptions::defaults()
                ->withRateLimiter($this->rateLimiter)
                ->withIdGenerator($this->ids)
                ->withClock($this->clock)
                ->withLogger($this->logger),
        );
    }


    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return array_values($this->http->getRequests());
    }

    public function lastRequest(): RequestInterface
    {
        $requests = $this->requests();
        $last = array_pop($requests);

        return $last ?? throw new LogicException('No request was sent.');
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(int $status, string $body, array $headers = []): void
    {
        $this->http->addResponse(new Response($status, $headers, $body));
    }

    public function respondToken(): void
    {
        $this->respond(200, Fixtures::load('token_response'));
    }

    public function fail(TransportFailure $exception): void
    {
        $this->http->addException($exception);
    }
}
