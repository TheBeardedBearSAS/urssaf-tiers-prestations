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
 * Meaning of a refused URSSAF call ({@see UrssafRequestRejected}), as classified by the gateway from the URSSAF error
 * codes, so that the application never compares wire codes.
 */
enum RefusalReason: string
{
    /** Validation or business errors without a dedicated meaning. */
    case Generic = 'generic';

    /** Method 020: the customer has no active registration with this provider any more. */
    case NoActiveRegistration = 'no_active_registration';

    /** Method 010: the customer is already registered with this provider (its URSSAF id is not returned). */
    case AlreadyRegistered = 'already_registered';
}
