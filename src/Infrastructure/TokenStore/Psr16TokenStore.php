<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore;

use Psr\SimpleCache\CacheInterface;
use SensitiveParameter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\AccessToken;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TokenStore;

/**
 * Token cache on a PSR-16 simple cache. The entry TTL is the time left before the token expires (computed with the
 * injected clock); a token that is already expired is not kept. An entry that is missing or corrupted is deleted.
 */
final readonly class Psr16TokenStore implements TokenStore
{
    public function __construct(
        private CacheInterface $cache,
        private Clock $clock,
    ) {}

    public function get(string $key): ?AccessToken
    {
        $cacheKey = CacheKey::of($key);
        $token = CachedAccessToken::fromArray($this->cache->get($cacheKey));
        if (!$token instanceof AccessToken) {
            $this->cache->delete($cacheKey);
        }

        return $token;
    }

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void
    {
        $cacheKey = CacheKey::of($key);
        $ttl = $token->expiresAt()->getTimestamp() - $this->clock->now()->getTimestamp();
        if ($ttl <= 0) {
            $this->cache->delete($cacheKey);

            return;
        }

        $this->cache->set($cacheKey, CachedAccessToken::toArray($token), $ttl);
    }

    public function delete(string $key): void
    {
        $this->cache->delete(CacheKey::of($key));
    }
}
