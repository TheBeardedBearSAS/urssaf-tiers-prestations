<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class JsonApiClientRetryTest extends TestCase
{
    private const RENEWAL_RESPONSE = '{"access_token":"second-token","expires_in":3600}';

    public function testA401InvalidatesTheTokenAndReplaysTheCallExactlyOnce(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, self::RENEWAL_RESPONSE);
        $stack->respond(200, '{"ok":"retried"}');

        $payload = $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::GetCustomerStatus, '/p', []),
        );

        static::assertSame('retried', $payload->string('ok'));
        $requests = $stack->requests();
        static::assertCount(4, $requests);
        static::assertSame('Bearer fictitious-access-token-0001', $requests[1]->getHeaderLine('Authorization'));
        static::assertSame('Bearer second-token', $requests[3]->getHeaderLine('Authorization'));
        static::assertSame('request-id-1', $requests[1]->getHeaderLine('x-request-id'));
        static::assertSame('request-id-2', $requests[3]->getHeaderLine('x-request-id'));
    }

    public function testTheReplayAcquiresTheRateLimiterAgain(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, self::RENEWAL_RESPONSE);
        $stack->respond(200, '{}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::GetCustomerStatus, '/p', []),
        );

        static::assertCount(2, $stack->rateLimiter->acquired());
    }

    public function testASecond401FailsAuthenticationWithoutAThirdAttempt(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(401, '');
        $stack->respond(200, self::RENEWAL_RESPONSE);
        $stack->respond(401, '');

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::get(Operation::GetCustomerStatus, '/p', []),
            );
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame(401, $exception->httpStatus());
        }
        static::assertCount(4, $stack->requests());
    }

    public function testOtherStatusesAreNeverReplayed(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(403, '');

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::get(Operation::GetCustomerStatus, '/p', []),
            );
            static::fail('Expected an authentication failure.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame(403, $exception->httpStatus());
        }
        static::assertCount(2, $stack->requests());
        static::assertCount(1, $stack->tokens->keys());
    }
}
