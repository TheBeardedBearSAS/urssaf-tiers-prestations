<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http;

use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\Payload;

/**
 * Successful answer of an API call, with the `x-request-id` sent with it (for correlation in logs).
 *
 * @internal
 */
final readonly class ApiResponse
{
    public function __construct(private Payload $payload, private string $requestId) {}

    public function payload(): Payload
    {
        return $this->payload;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }
}
