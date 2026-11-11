<?php

declare(strict_types=1);

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
