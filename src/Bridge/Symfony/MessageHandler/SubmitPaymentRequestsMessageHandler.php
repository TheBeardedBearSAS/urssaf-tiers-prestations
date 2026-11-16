<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler;

use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitPaymentRequestsMessage;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

final readonly class SubmitPaymentRequestsMessageHandler
{
    public function __construct(private SubmitPaymentRequestsHandler $submit, private OperationLocks $locks) {}

    public function __invoke(SubmitPaymentRequestsMessage $message): void
    {
        $this->locks->exclusively(
            'payment-requests.submit',
            $message->tenantId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->submit)(SubmitPaymentRequests::of(
                TenantId::fromString($message->tenantId),
                ...array_map(LocalPaymentRequestId::fromString(...), $message->paymentRequestIds),
            ))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
