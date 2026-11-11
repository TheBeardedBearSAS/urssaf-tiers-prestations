<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

/**
 * URSSAF API operations subject to a quota (HTTP 429 when exceeded).
 */
enum Operation: string
{
    /** 010 POST /particulier */
    case RegisterCustomer = 'register_customer';

    /** 020 GET /particulier */
    case GetCustomerStatus = 'get_customer_status';

    /** 050 POST /demandePaiement */
    case SubmitPaymentRequests = 'submit_payment_requests';

    /** 070 POST /demandePaiement/rechercher */
    case SearchPaymentRequests = 'search_payment_requests';

    /**
     * Official number of the method in the URSSAF documentation ("010", "020", "050", "070").
     */
    public function methodNumber(): string
    {
        return match ($this) {
            self::RegisterCustomer => '010',
            self::GetCustomerStatus => '020',
            self::SubmitPaymentRequests => '050',
            self::SearchPaymentRequests => '070',
        };
    }

    /**
     * Official quota, in calls per minute.
     */
    public function quotaPerMinute(): int
    {
        return match ($this) {
            self::RegisterCustomer => 100,
            self::GetCustomerStatus, self::SearchPaymentRequests => 300,
            self::SubmitPaymentRequests => 150,
        };
    }
}
