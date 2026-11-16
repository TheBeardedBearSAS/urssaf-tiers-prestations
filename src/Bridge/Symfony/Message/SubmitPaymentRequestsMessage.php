<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

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
