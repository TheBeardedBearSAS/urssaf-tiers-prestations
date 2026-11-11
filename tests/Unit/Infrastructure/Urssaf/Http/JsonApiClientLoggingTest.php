<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http\ApiRequest;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\TransportFailure;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class JsonApiClientLoggingTest extends TestCase
{
    public function testItLogsMethodPathStatusDurationAndRequestId(): void
    {
        $stack = Stacks::stepping(250_000);
        $stack->respondToken();
        $stack->respond(200, '{"secret_looking":"patient name"}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::get(Operation::GetCustomerStatus, '/atp/v1/tiersPrestations/particulier', ['idClient' => 'abc']),
        );

        $records = $stack->logger->records();
        static::assertCount(2, $records);
        static::assertSame('info', $records[1]['level']);
        static::assertSame(
            [
                'method' => 'GET',
                'path' => '/atp/v1/tiersPrestations/particulier',
                'request_id' => 'request-id-1',
                'status' => 200,
                'duration_ms' => 250,
            ],
            $records[1]['context'],
        );
    }

    public function testNeitherSecretsTokensNorBodiesAreLogged(): void
    {
        $stack = Stacks::stepping(1000);
        $stack->respondToken();
        $stack->respond(200, '{"nomNaissance":"Ledru"}');

        $stack->jsonClient()->send(
            Stacks::tenant(),
            $stack->credentials->forTenant(Stacks::tenant()),
            ApiRequest::post(Operation::SubmitPaymentRequests, '/p', '{"iban":"FR7630006000011234567890189"}'),
        );

        $dump = $stack->logger->dump();
        static::assertStringNotContainsString(StaticCredentialsProvider::SHARED_VALUE, $dump);
        static::assertStringNotContainsString('fictitious-access-token-0001', $dump);
        static::assertStringNotContainsString('Bearer', $dump);
        static::assertStringNotContainsString('FR7630006000011234567890189', $dump);
        static::assertStringNotContainsString('Ledru', $dump);
        static::assertStringNotContainsString('idClient', $dump);
    }

    public function testItLogsTransportFailuresAsWarnings(): void
    {
        $stack = Stacks::stepping(250_000);
        $stack->warmUpToken();
        $stack->fail(new TransportFailure('timeout'));

        try {
            $stack->jsonClient()->send(
                Stacks::tenant(),
                $stack->credentials->forTenant(Stacks::tenant()),
                ApiRequest::get(Operation::GetCustomerStatus, '/p', []),
            );
            static::fail('Expected an unavailable exception.');
        } catch (UrssafUnavailable) {
            $records = $stack->logger->records();
            static::assertCount(1, $records);
            static::assertSame('warning', $records[0]['level']);
            static::assertSame(
                [
                    'method' => 'GET',
                    'path' => '/p',
                    'request_id' => 'request-id-1',
                    'duration_ms' => 250,
                    'exception' => TransportFailure::class,
                ],
                $records[0]['context'],
            );
        }
    }
}
