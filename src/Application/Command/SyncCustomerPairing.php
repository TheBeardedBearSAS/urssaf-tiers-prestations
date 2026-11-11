<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Polls method 020 for the registrations of a tenant waiting for (or having lost) the pairing.
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SyncCustomerPairing
{
    public function __construct(public TenantId $tenantId, public ?int $limit) {}
}
