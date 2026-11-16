<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\JsonApiClient;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\RejectingRateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\SequenceIdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class JsonApiClientRequestTest extends TestCase
{
    public function testItSendsAnAuthenticatedJsonGetRequest(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"ok":"yes"}');

        $payload = $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::GetCustomerStatus, '/atp/v1/tiersPrestations/particulier', ['idClient' => 'abc']),
        );

        static::assertSame('yes', $payload->string('ok'));
        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/atp/v1/tiersPrestations/particulier?idClient=abc',
            (string) $request->getUri(),
        );
        static::assertSame('Bearer fictitious-access-token-0001', $request->getHeaderLine('Authorization'));
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertSame('request-id-1', $request->getHeaderLine('x-request-id'));
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $request->getHeaderLine('User-Agent'));
        static::assertFalse($request->hasHeader('recouv.application.id'));
        static::assertSame('', (string) $request->getBody());
    }

    public function testItSendsThePostBodyVerbatimWithoutAQueryString(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '[]');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::post(Operation::SubmitPaymentRequests, '/atp/v1/tiersPrestations/demandePaiement', '[{"mntFactureTTC":11.02}]'),
        );

        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/atp/v1/tiersPrestations/demandePaiement', (string) $request->getUri());
        static::assertSame('[{"mntFactureTTC":11.02}]', (string) $request->getBody());
    }

    public function testItEncodesQueryValuesAccordingToRfc3986(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::GetCustomerStatus, '/p', ['idClient' => 'a b&c', 'x' => 'y']),
        );

        static::assertSame('idClient=a%20b%26c&x=y', $stack->lastRequest()->getUri()->getQuery());
    }

    public function testItAddsTheApplicationIdHeaderWhenConfigured(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(Environment::Production, null, 'app-42'));
        $stack->respondToken();
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::GetCustomerStatus, '/p', []),
        );

        $request = $stack->lastRequest();
        static::assertSame('app-42', $request->getHeaderLine('recouv.application.id'));
        static::assertSame('https://api.urssaf.fr/p', (string) $request->getUri());
    }

    public function testItAcquiresTheRateLimiterForTheOperationOfEachCall(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{}');
        $stack->respond(200, '{}');
        $client = $stack->jsonClient();
        $credentials = $stack->credentials->forTenant(Stacks::tenant());

        $client->send(Stacks::tenant(), $credentials, ApiRequest::get(Operation::GetCustomerStatus, '/p', []));
        $client->send(Stacks::tenant(), $credentials, ApiRequest::post(Operation::SearchPaymentRequests, '/q', '{}'));

        static::assertSame(
            ['tenant-1:get_customer_status', 'tenant-1:search_payment_requests'],
            $stack->rateLimiter->acquired(),
        );
    }

    public function testAnExceededRateLimitStopsBeforeAnyHttpCall(): void
    {
        $stack = Stacks::sandbox();
        $client = new JsonApiClient(
            $stack->http,
            new Psr17Factory(),
            new Psr17Factory(),
            $stack->tokenProvider(),
            new RejectingRateLimiter(),
            new SequenceIdGenerator(),
            $stack->clock,
            $stack->configuration,
        );

        try {
            $client->send(Stacks::tenant(), $stack->credentials->forTenant(Stacks::tenant()), ApiRequest::get(Operation::GetCustomerStatus, '/p', []));
            static::fail('Expected the rate limit to be enforced.');
        } catch (RateLimitExceeded $exception) {
            static::assertSame(30, $exception->retryAfterSeconds());
        }
        static::assertSame([], $stack->requests());
    }
}
