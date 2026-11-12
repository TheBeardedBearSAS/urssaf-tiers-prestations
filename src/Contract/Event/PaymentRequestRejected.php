<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Contract\Event;

/**
 * URSSAF rejected the payment request at submission (method 050, final): the invoice must be corrected.
 */
final readonly class PaymentRequestRejected extends IntegrationEvent
{
    public const TYPE = 'urssaf.payment_request.rejected';

    /**
     * @param list<string> $errorCodes the URSSAF error codes
     */
    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public array $errorCodes,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
