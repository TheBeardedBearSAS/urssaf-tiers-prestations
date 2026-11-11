<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Polls method 070 for the submitted payment requests of a tenant whose status may still change.
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SyncPaymentRequestStatuses
{
    public function __construct(public TenantId $tenantId, public ?int $limit) {}
}
