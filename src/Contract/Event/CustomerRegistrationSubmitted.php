<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Contract\Event;

/**
 * URSSAF accepted the registration of the customer (method 010): the customer must now accept the pairing.
 */
final readonly class CustomerRegistrationSubmitted extends IntegrationEvent
{
    public const TYPE = 'urssaf.customer_registration.submitted';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $urssafCustomerId,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
