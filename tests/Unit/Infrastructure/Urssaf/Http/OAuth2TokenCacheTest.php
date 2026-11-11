<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Http;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class OAuth2TokenCacheTest extends TestCase
{
    private function credentials(string $clientId = StaticCredentialsProvider::CLIENT_ID): Credentials
    {
        return Credentials::of($clientId, StaticCredentialsProvider::SHARED_VALUE);
    }

    private const ISSUED = 'fictitious-access-token-0001';

    public function testItCachesTheTokenUnderAPsr6SafeKeyWithoutSecrets(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $provider = $stack->tokenProvider();

        $provider->accessToken($this->credentials(), Operation::GetCustomerStatus);
        $second = $provider->accessToken($this->credentials(), Operation::SearchPaymentRequests);

        static::assertSame(self::ISSUED, $second);
        static::assertCount(1, $stack->requests());
        $expectedKey = 'urssaf_tps.' . hash('sha256', 'sandbox|https://api-edi.urssaf.fr|' . StaticCredentialsProvider::CLIENT_ID);
        static::assertSame([$expectedKey], $stack->tokens->keys());
        static::assertMatchesRegularExpression('/^[A-Za-z0-9_.]+$/', $expectedKey);
        static::assertSame(
            'urssaf_tps.' . hash('sha256', 'sandbox|https://api-edi.urssaf.fr|' . StaticCredentialsProvider::CLIENT_ID),
            $stack->tokens->keys()[0],
        );
    }

    public function testTheCacheKeyDependsOnClientEnvironmentAndBaseUri(): void
    {
        $keys = [];
        $variants = [
            [ApiConfiguration::forEnvironment(Environment::Sandbox), 'client-a'],
            [ApiConfiguration::forEnvironment(Environment::Sandbox), 'client-b'],
            [ApiConfiguration::forEnvironment(Environment::Production), 'client-a'],
            [new ApiConfiguration(Environment::Sandbox, 'https://proxy.example.test', null), 'client-a'],
        ];
        foreach ($variants as [$configuration, $clientId]) {
            $stack = Stacks::configured($configuration);
            $stack->respondToken();
            $stack->tokenProvider()->accessToken($this->credentials($clientId), Operation::GetCustomerStatus);
            $keys[] = $stack->tokens->keys()[0];
        }

        static::assertCount(4, array_unique($keys));
    }

    public function testInvalidateForcesANewToken(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetCustomerStatus);

        $provider->invalidate($this->credentials());

        static::assertSame([], $stack->tokens->keys());
        static::assertSame('second-token', $provider->accessToken($this->credentials(), Operation::GetCustomerStatus));
    }

    public function testInvalidateOnlyAffectsTheGivenClient(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respondToken();
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials('client-a'), Operation::GetCustomerStatus);
        $provider->accessToken($this->credentials('client-b'), Operation::GetCustomerStatus);

        $provider->invalidate($this->credentials('client-a'));

        static::assertCount(1, $stack->tokens->keys());
    }
}
