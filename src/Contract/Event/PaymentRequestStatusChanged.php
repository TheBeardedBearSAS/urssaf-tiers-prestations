<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Contract\Event;

/**
 * A poll (method 070) observed a new URSSAF status of the payment request.
 *
 * - statusCode: the URSSAF code ("10" integrated ... "70" paid ... "270" regularised), kept even when unknown;
 * - final: no other status will follow; settled: paid or recovered (the transfer details are available with
 *   FindPaymentRequestsHandler).
 */
final readonly class PaymentRequestStatusChanged extends IntegrationEvent
{
    public const TYPE = 'urssaf.payment_request.status_changed';

    /**
     * @param ?string $previousStatusCode null when no status was known before
     */
    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public ?string $previousStatusCode,
        public string $statusCode,
        public bool $final,
        public bool $settled,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
