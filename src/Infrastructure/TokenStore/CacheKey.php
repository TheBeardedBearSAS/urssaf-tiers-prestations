<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore;

/**
 * Maps an opaque token key to a cache-safe key. Keys made of letters, digits, "_", "." and "-" (at most 64 characters)
 * are kept verbatim; any other key (PSR-6/PSR-16 reserved characters "{}()/\@:" included) is replaced by a SHA-256
 * digest with a "hashed_" prefix, which keeps hashed keys disjoint from verbatim ones (71 characters, never verbatim).
 */
final class CacheKey
{
    private const VERBATIM_PATTERN = '/^[A-Za-z0-9_.-]{1,64}$/';
    private const HASHED_PREFIX = 'hashed_';

    public static function of(string $key): string
    {
        if (preg_match(self::VERBATIM_PATTERN, $key) === 1) {
            return $key;
        }

        return self::HASHED_PREFIX . hash('sha256', $key);
    }
}
