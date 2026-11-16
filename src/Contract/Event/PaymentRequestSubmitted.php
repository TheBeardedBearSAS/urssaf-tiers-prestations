<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Contract\Event;

/**
 * URSSAF accepted the payment request (method 050); its status is then followed by PaymentRequestStatusChanged.
 */
final readonly class PaymentRequestSubmitted extends IntegrationEvent
{
    public const TYPE = 'urssaf.payment_request.submitted';

    public function __construct(
        string $eventId,
        string $occurredAt,
        string $tenantId,
        string $aggregateId,
        string $externalReference,
        public string $urssafPaymentRequestId,
    ) {
        parent::__construct($eventId, $occurredAt, $tenantId, $aggregateId, $externalReference);
    }
}
