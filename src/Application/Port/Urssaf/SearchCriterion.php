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
 * Exclusive criteria of method 070, in URSSAF evaluation order.
 */
enum SearchCriterion: string
{
    case PaymentRequestIds = 'payment_request_ids';
    case InvoiceNumbers = 'invoice_numbers';
    case InvoicePeriod = 'invoice_period';
}
