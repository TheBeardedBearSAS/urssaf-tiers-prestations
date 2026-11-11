<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message;

/**
 * Asks to poll the status of the submitted payment requests of one tenant (method 070).
 */
final readonly class SyncTenantPaymentStatusesMessage
{
    public function __construct(public string $tenantId) {}
}
