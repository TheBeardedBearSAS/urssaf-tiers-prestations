<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use SensitiveParameter;

/**
 * Cache of access tokens. Keys are opaque strings built by the authenticator (one per tenant and environment);
 * implementations must not log token values.
 */
interface TokenStore
{
    public function get(string $key): ?AccessToken;

    public function save(string $key, #[SensitiveParameter] AccessToken $token): void;

    public function delete(string $key): void;
}
