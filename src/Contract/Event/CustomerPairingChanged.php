<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Contract\Event;

/**
 * The pairing status of the customer changed (method 020).
 *
 * - pairingStatus: the URSSAF status (APPAREILLAGE_VALIDE, APPAREILLAGE_EC, PARTICULIER_BLOQUE, MANDAT_ECHU...);
 * - state: the new state of the registration (registered, awaiting_pairing, blocked, mandate_expired...);
 * - registered: true when payment requests may be sent for the customer.
 */
final readonly class CustomerPairingChanged extends IntegrationEvent
{
    public const TYPE = 'urssaf.customer_registration.pairing_changed';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $pairingStatus,
        public string $state,
        public bool $registered,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
