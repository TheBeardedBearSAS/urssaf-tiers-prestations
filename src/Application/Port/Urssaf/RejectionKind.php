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

/**
 * Why method 050 did not integrate one payment request, as classified by the gateway from the URSSAF error codes, and
 * therefore what the application must do with it.
 */
enum RejectionKind: string
{
    /** Business or validation errors: the request must be fixed and created again. */
    case Final = 'final';

    /** Technical error on the URSSAF side (only technical errors): the request may be sent again. */
    case Retryable = 'retryable';

    /** The customer account is blocked: the request is refused and the registration is blocked. */
    case CustomerBlocked = 'customer_blocked';

    /** No valid direct debit mandate: the request is refused and the registration mandate has expired. */
    case MandateExpired = 'mandate_expired';

    /** No customer / provider link over the period: the request is refused and the pairing must be checked again. */
    case PairingMissing = 'pairing_missing';

    /**
     * The invoice number is already known by URSSAF: the request may have been integrated by an earlier attempt, so
     * it is never rejected on this ground; it is reconciled by searching its invoice number (method 070).
     */
    case Duplicate = 'duplicate';
}
