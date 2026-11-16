<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

enum RejectionContext: string
{
    /** The provider must regularise the invoice. */
    case Regularisation = 'regularisation';

    /** The customer contested the request (status 40). */
    case Contestation = 'contestation';

    /** The request was cancelled (status 110). */
    case Cancellation = 'cancellation';
}
