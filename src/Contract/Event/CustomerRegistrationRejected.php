<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Contract\Event;

/**
 * URSSAF refused the registration (final): the customer data must be corrected and registered again.
 */
final readonly class CustomerRegistrationRejected extends IntegrationEvent
{
    public const TYPE = 'urssaf.customer_registration.rejected';

    /**
     * @param list<string> $errorCodes the URSSAF error codes, or the violation codes of the package
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
