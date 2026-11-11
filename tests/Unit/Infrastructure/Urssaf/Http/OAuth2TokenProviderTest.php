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

final class OAuth2TokenProviderTest extends TestCase
{
    private const ISSUED = 'fictitious-access-token-0001';

    private function credentials(string $clientId = StaticCredentialsProvider::CLIENT_ID): Credentials
    {
        return Credentials::of($clientId, StaticCredentialsProvider::SHARED_VALUE);
    }

    public function testItRequestsATokenWithTheClientCredentialsGrant(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();

        $token = $stack->tokenProvider()->accessToken($this->credentials(), Operation::GetCustomerStatus);

        static::assertSame(self::ISSUED, $token);
        $request = $stack->lastRequest();
        static::assertSame('POST', $request->getMethod());
        static::assertSame('https://api-edi.urssaf.fr/api/oauth/v1/token', (string) $request->getUri());
        static::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        static::assertSame('application/json', $request->getHeaderLine('Accept'));
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $request->getHeaderLine('User-Agent'));
        $form = [];
        parse_str((string) $request->getBody(), $form);
        static::assertSame(
            [
                'grant_type' => 'client_credentials',
                'client_id' => StaticCredentialsProvider::CLIENT_ID,
                'client_secret' => StaticCredentialsProvider::SHARED_VALUE,
                'scope' => 'homeplus.tiersprestations',
            ],
            $form,
        );
    }

    public function testItUsesTheProductionHostWhenConfigured(): void
    {
        $stack = Stacks::configured(ApiConfiguration::forEnvironment(Environment::Production));
        $stack->respondToken();

        $stack->tokenProvider()->accessToken($this->credentials(), Operation::GetCustomerStatus);

        static::assertSame('https://api.urssaf.fr/api/oauth/v1/token', (string) $stack->lastRequest()->getUri());
    }

    public function testItRefreshesAnExpiredToken(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetCustomerStatus);

        $stack->clock->advanceSeconds(3600);
        $token = $provider->accessToken($this->credentials(), Operation::GetCustomerStatus);

        static::assertSame('second-token', $token);
        static::assertCount(2, $stack->requests());
    }

    public function testItRefreshesTheTokenWithinTheSafetyMargin(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetCustomerStatus);

        $stack->clock->advanceSeconds(3540);

        static::assertSame('second-token', $provider->accessToken($this->credentials(), Operation::GetCustomerStatus));
    }

    public function testItKeepsTheTokenJustBeforeTheSafetyMargin(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetCustomerStatus);

        $stack->clock->advanceSeconds(3539);

        static::assertSame(self::ISSUED, $provider->accessToken($this->credentials(), Operation::GetCustomerStatus));
        static::assertCount(1, $stack->requests());
    }

    public function testTheSafetyMarginIsConfigurable(): void
    {
        $stack = Stacks::configured(new ApiConfiguration(Environment::Sandbox, null, null, 600));
        $stack->respondToken();
        $stack->respond(200, '{"access_token":"second-token","expires_in":3600}');
        $provider = $stack->tokenProvider();
        $provider->accessToken($this->credentials(), Operation::GetCustomerStatus);

        $stack->clock->advanceSeconds(3000);

        static::assertSame('second-token', $provider->accessToken($this->credentials(), Operation::GetCustomerStatus));
    }
}
