<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafEndpoints;

final class ApiConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{Environment, string}>
     */
    public static function environments(): iterable
    {
        yield 'sandbox' => [Environment::Sandbox, 'https://api-edi.urssaf.fr'];
        yield 'production' => [Environment::Production, 'https://api.urssaf.fr'];
    }

    #[DataProvider('environments')]
    public function testItResolvesTheEnvironmentHost(Environment $environment, string $expected): void
    {
        $configuration = ApiConfiguration::forEnvironment($environment);

        static::assertSame($expected, $configuration->baseUri());
        static::assertSame($environment, $configuration->environment());
    }

    public function testItBuildsTokenAndApiUris(): void
    {
        $configuration = ApiConfiguration::forEnvironment(Environment::Production);

        static::assertSame('https://api.urssaf.fr/api/oauth/v1/token', $configuration->tokenUri());
        static::assertSame(
            'https://api.urssaf.fr/atp/v1/tiersPrestations/particulier',
            $configuration->apiUri(UrssafEndpoints::CUSTOMER_PATH),
        );
    }

    public function testItAppliesTheBaseUriOverrideWithoutTrailingSlash(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, 'https://proxy.example.test/', null);

        static::assertSame('https://proxy.example.test', $configuration->baseUri());
    }

    public function testItHasSensibleDefaults(): void
    {
        $configuration = ApiConfiguration::forEnvironment(Environment::Sandbox);

        static::assertNull($configuration->applicationId());
        static::assertSame(60, $configuration->tokenSafetyMarginSeconds());
        static::assertSame(ApiConfiguration::DEFAULT_USER_AGENT, $configuration->userAgent());
        static::assertSame('Europe/Paris', $configuration->pairingTimeZone()->getName());
    }

    public function testItExposesThePairingTimeZone(): void
    {
        $timeZone = new DateTimeZone('UTC');

        static::assertSame($timeZone, (new ApiConfiguration(Environment::Sandbox, null, null, 5, 'agent', $timeZone))->pairingTimeZone());
    }

    public function testItExposesCustomSettings(): void
    {
        $configuration = new ApiConfiguration(Environment::Sandbox, null, 'app-42', 5, 'my-agent');

        static::assertSame('app-42', $configuration->applicationId());
        static::assertSame(5, $configuration->tokenSafetyMarginSeconds());
        static::assertSame('my-agent', $configuration->userAgent());
    }

    public function testItAcceptsAZeroSafetyMargin(): void
    {
        static::assertSame(0, (new ApiConfiguration(Environment::Sandbox, null, null, 0))->tokenSafetyMarginSeconds());
    }

    public function testItRejectsANegativeSafetyMargin(): void
    {
        $this->expectException(InvalidValue::class);

        new ApiConfiguration(Environment::Sandbox, null, null, -1);
    }
}
