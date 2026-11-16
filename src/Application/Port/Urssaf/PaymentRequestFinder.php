<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Method 070 - POST /demandePaiement/rechercher.
 */
interface PaymentRequestFinder
{
    /**
     * ERR_RECHERCHE_VIDE (no match) is NOT an error: an empty list is returned.
     *
     * @return list<PaymentRequestSnapshot>
     *
     * @throws UrssafRequestRejected      e.g. ERR_NBRE_MAX_RESULTAT (period search with more than 10 results)
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable          retryable
     */
    public function search(TenantId $tenantId, PaymentRequestSearch $search): array;
}
