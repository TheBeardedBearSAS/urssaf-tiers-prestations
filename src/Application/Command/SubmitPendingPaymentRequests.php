<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Submits the oldest pending payment requests of a tenant (see SubmitPaymentRequests).
 *
 * The limit is capped by the workflow policy (default page size when null).
 */
final readonly class SubmitPendingPaymentRequests
{
    public function __construct(public TenantId $tenantId, public ?int $limit) {}
}
