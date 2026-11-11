<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http;

use DateInterval;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\AccessToken;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TokenStore;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafEndpoints;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonDecoder;

/**
 * OAuth2 client-credentials flow with a shared token cache. Build the gateway with
 * {@see \TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafGatewayFactory}.
 *
 * The cache key is `urssaf_tps.<sha256(environment|baseUri|clientId)>` (PSR-6 safe, never contains the secret).
 * Nothing secret (client secret, token) is ever logged.
 *
 * @internal
 */
final readonly class OAuth2TokenProvider
{
    private const KEY_PREFIX = 'urssaf_tps.';

    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private TokenStore $tokenStore,
        private Clock $clock,
        private ApiConfiguration $configuration,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Returns a valid bearer token, fetching a new one when the cached one is missing or about to expire.
     *
     * @throws UrssafAuthenticationFailed token endpoint answered 400/401/403
     * @throws UrssafUnavailable          token endpoint answered 429/5xx or could not be reached (the API call itself
     *                                    is not sent: {@see UrssafUnavailable::mayHaveReachedUrssaf()} is false)
     * @throws UnexpectedUrssafResponse
     */
    public function accessToken(Credentials $credentials, Operation $operation): string
    {
        $key = $this->cacheKey($credentials);
        $cached = $this->tokenStore->get($key);

        if ($cached instanceof AccessToken && !$cached->isExpired(
            $this->clock->now(),
            $this->configuration->tokenSafetyMarginSeconds(),
        )) {
            return $cached->value();
        }

        $token = $this->fetch($credentials, $operation);
        $this->tokenStore->save($key, $token);

        return $token->value();
    }

    public function invalidate(Credentials $credentials): void
    {
        $this->tokenStore->delete($this->cacheKey($credentials));
    }

    private function cacheKey(Credentials $credentials): string
    {
        return self::KEY_PREFIX . hash('sha256', \sprintf(
            '%s|%s|%s',
            $this->configuration->environment()->value,
            $this->configuration->baseUri(),
            $credentials->clientId(),
        ));
    }

    private function fetch(Credentials $credentials, Operation $operation): AccessToken
    {
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $credentials->clientId(),
            'client_secret' => $credentials->clientSecret(),
            'scope' => UrssafEndpoints::TOKEN_SCOPE,
        ], '', '&', PHP_QUERY_RFC3986);

        $request = $this->requestFactory
            ->createRequest('POST', $this->configuration->tokenUri())
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->configuration->userAgent())
            ->withBody($this->streamFactory->createStream($body));

        $startedAt = $this->clock->now();

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            $this->logger->warning('URSSAF token request failed: transport error.', ['exception' => $exception::class]);

            throw UrssafUnavailable::whileAuthenticating($operation, null, null, $exception);
        }

        $this->logger->info('URSSAF token request.', [
            'method' => 'POST',
            'path' => UrssafEndpoints::TOKEN_PATH,
            'status' => $response->getStatusCode(),
            'duration_ms' => Duration::millisecondsSince($startedAt, $this->clock->now()),
        ]);

        return $this->tokenFrom($response, $operation);
    }

    private function tokenFrom(ResponseInterface $response, Operation $operation): AccessToken
    {
        $status = $response->getStatusCode();

        if (\in_array($status, [400, 401, 403], true)) {
            throw UrssafAuthenticationFailed::withStatus($status);
        }

        if ($status === 429 || $status >= 500) {
            throw UrssafUnavailable::whileAuthenticating($operation, $status, RetryAfter::seconds($response), null);
        }

        if ($status < 200 || $status >= 300) {
            throw UnexpectedUrssafResponse::unexpectedStatus($operation, $status);
        }

        $payload = JsonDecoder::decode((string) $response->getBody());
        $lifetime = $payload->number('expires_in');
        if ($lifetime === null || (int) $lifetime <= 0) {
            throw UnexpectedUrssafResponse::invalidPayload('field "expires_in" is missing or not positive');
        }

        return AccessToken::of(
            $payload->requireString('access_token'),
            $this->clock->now()->add(new DateInterval('PT' . (int) $lifetime . 'S')),
        );
    }
}
