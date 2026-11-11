<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Method 010 - POST /particulier.
 */
interface CustomerRegistrar
{
    /**
     * @throws UrssafRequestRejected      business refusal (e.g. ERR_DGFIP_R2P_INCONNU, ERR_COMPTE_BLOQUE), see
     *                                    {@see UrssafRequestRejected::isAlreadyRegistered()}
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable; see {@see UrssafUnavailable::mayHaveReachedUrssaf()}
     */
    public function register(TenantId $tenantId, CustomerRegistration $registration): UrssafCustomerId;
}
