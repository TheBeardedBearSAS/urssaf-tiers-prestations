<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Method 020 - GET /particulier?idClient=... (always queried by customer id, lower-case).
 */
interface TransmissionStatusReader
{
    /**
     * @throws UrssafRequestRejected      e.g. no active registration any more
     *                                    ({@see UrssafRequestRejected::isNoActiveRegistration()})
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable
     */
    public function transmissionStatus(TenantId $tenantId, UrssafCustomerId $customerId): CustomerTransmissionStatus;
}
