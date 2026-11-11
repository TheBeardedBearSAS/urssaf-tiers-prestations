<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Credentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

final class StaticCredentialsProvider implements CredentialsProvider
{
    public const CLIENT_ID = 'fictitious-client-id';
    public const SHARED_VALUE = 'fictitious-client-secret-value';

    public function forTenant(TenantId $tenantId): Credentials
    {
        return Credentials::of(self::CLIENT_ID, self::SHARED_VALUE);
    }
}
