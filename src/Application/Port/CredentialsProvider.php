<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

interface CredentialsProvider
{
    /**
     * @throws RecordNotFound "credentials.not_found" when the tenant has no credentials
     */
    public function forTenant(TenantId $tenantId): Credentials;
}
