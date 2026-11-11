<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Query;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Reads one customer registration of a tenant, by registration id or external reference.
 */
final readonly class GetCustomerRegistration
{
    public function __construct(public TenantId $tenantId, public CustomerSelector $customer) {}
}
