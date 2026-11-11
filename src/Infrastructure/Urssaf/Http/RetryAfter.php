<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Parses the `Retry-After` response header (delay in seconds form only; the HTTP-date form is ignored).
 */
final class RetryAfter
{
    public static function seconds(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));

        return preg_match('/^\d{1,9}$/', $value) === 1 ? (int) $value : null;
    }
}
