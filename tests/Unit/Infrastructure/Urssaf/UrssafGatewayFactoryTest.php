<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf;

use DateTimeImmutable;
use DateTimeZone;
use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\RateLimiter\NoopRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UtcSystemClock;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\InMemoryTokenStore;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\SteppingClock;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafGatewayFactoryTest extends TestCase
{
    private const CUSTOMER = 'af0be430-908a-11eb-a8b3-0242ac130003';

    public function testCreatesAWorkingGatewayWithTheDefaults(): void
    {
        $http = new Client();
        $http->addResponse(new Response(200, [], Fixtures::load('token_response')));
        $http->addResponse(new Response(200, [], Fixtures::load('customer_status_pairing_valid')));
        $factory = new Psr17Factory();
        $tokens = new InMemoryTokenStore();

        $gateway = UrssafGatewayFactory::create(
            new HttpTransport($http, $factory, $factory),
            new StaticCredentialsProvider(),
            $tokens,
            new ApiConfiguration(Environment::Sandbox, null, null, 60, 'agent', new DateTimeZone('UTC')),
        );
        $status = $gateway->transmissionStatus(Stacks::tenant(), UrssafCustomerId::fromString(self::CUSTOMER));

        static::assertSame('2023-03-15T14:30:05Z', $status->pairingAcceptedAt()?->toIso8601());
        $requests = $http->getRequests();
        static::assertCount(2, $requests);
        static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $requests[1]->getHeaderLine('x-request-id'));
        static::assertSame('agent', $requests[1]->getHeaderLine('User-Agent'));
        static::assertCount(1, $tokens->keys());
    }

    public function testTheDefaultRateLimiterEnforcesTheQuotaWithinTheProcess(): void
    {
        $http = new Client();
        $http->addResponse(new Response(200, [], Fixtures::load('token_response')));
        $http->setDefaultResponse(new Response(200, [], Fixtures::load('customer_status_pairing_in_progress')));
        $factory = new Psr17Factory();
        $gateway = UrssafGatewayFactory::createWith(
            new HttpTransport($http, $factory, $factory),
            new StaticCredentialsProvider(),
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
            GatewayOptions::defaults()->withClock(new SteppingClock(0)),
        );
        $calls = 0;

        try {
            while ($calls <= Operation::GetCustomerStatus->quotaPerMinute()) {
                $gateway->transmissionStatus(Stacks::tenant(), UrssafCustomerId::fromString(self::CUSTOMER));
                ++$calls;
            }
            static::fail('Expected the local rate limit.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(Operation::GetCustomerStatus->quotaPerMinute(), $calls);
            static::assertSame(60, $exception->retryAfterSeconds());
        }
    }

    public function testUsesTheGivenClockAndLogger(): void
    {
        $http = new Client();
        $http->addResponse(new Response(200, [], Fixtures::load('token_response')));
        $http->addResponse(new Response(200, [], Fixtures::load('customer_status_pairing_in_progress')));
        $factory = new Psr17Factory();
        $logger = new CapturingLogger();
        $gateway = UrssafGatewayFactory::createWith(
            new HttpTransport($http, $factory, $factory),
            new StaticCredentialsProvider(),
            new InMemoryTokenStore(),
            ApiConfiguration::forEnvironment(Environment::Sandbox),
            GatewayOptions::defaults()->withClock(new SteppingClock(250_000))->withLogger($logger),
        );

        $gateway->transmissionStatus(Stacks::tenant(), UrssafCustomerId::fromString(self::CUSTOMER));

        $records = $logger->records();
        static::assertSame(['URSSAF token request.', 'URSSAF call.'], array_column($records, 'message'));
        static::assertSame(250, $records[1]['context']['duration_ms'] ?? null);
    }

    public function testOptionsKeepEveryGivenCollaborator(): void
    {
        $rateLimiter = new NoopRateLimiter();
        $ids = new SequenceIdGenerator();
        $clock = new UtcSystemClock();
        $logger = new NullLogger();

        $defaults = GatewayOptions::defaults();
        $options = $defaults->withRateLimiter($rateLimiter)->withIdGenerator($ids)->withClock($clock)->withLogger($logger);

        static::assertNull($defaults->rateLimiter());
        static::assertNull($defaults->idGenerator());
        static::assertNull($defaults->clock());
        static::assertNull($defaults->logger());
        static::assertSame($rateLimiter, $options->rateLimiter());
        static::assertSame($ids, $options->idGenerator());
        static::assertSame($clock, $options->clock());
        static::assertSame($logger, $options->logger());
    }

    public function testTheDefaultClockIsTheSystemTimeInUtc(): void
    {
        $before = new DateTimeImmutable();
        $now = (new UtcSystemClock())->now();

        static::assertSame('UTC', $now->getTimezone()->getName());
        static::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        static::assertLessThanOrEqual(time(), $now->getTimestamp());
    }
}
