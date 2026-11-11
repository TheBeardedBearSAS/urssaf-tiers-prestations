<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message;

/**
 * Asks to poll the pairing status of the customers of one tenant (method 020).
 */
final readonly class SyncTenantCustomerPairingMessage
{
    public function __construct(public string $tenantId) {}
}
