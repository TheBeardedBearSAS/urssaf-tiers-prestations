<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message;

/**
 * Asks to send pending payment requests of one tenant to URSSAF (method 050).
 */
final readonly class SubmitPaymentRequestsMessage
{
    /**
     * @param list<string> $paymentRequestIds local payment request ids
     */
    public function __construct(public string $tenantId, public array $paymentRequestIds) {}
}
