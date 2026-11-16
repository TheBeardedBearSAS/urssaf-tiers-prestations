<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestRejectedByUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestStatusChanged as DomainPaymentRequestStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestSubmitted as DomainPaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;

/**
 * Integration events of the payment request workflow.
 */
final class PaymentRequestEventTranslator
{
    /**
     * @return list<IntegrationEvent> the integration event of the domain event, none when it has none
     */
    public static function translate(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof DomainPaymentRequestSubmitted => [self::paymentRequestSubmitted($event)],
            $event instanceof PaymentRequestRejectedByUrssaf => [self::paymentRequestRejected($event)],
            $event instanceof DomainPaymentRequestStatusChanged => [self::statusChanged($event)],
            default => [],
        };
    }

    private static function paymentRequestSubmitted(DomainPaymentRequestSubmitted $event): PaymentRequestSubmitted
    {
        $aggregateId = $event->paymentRequestId()->value();
        $urssafId = $event->urssafPaymentRequestId()->value();

        return new PaymentRequestSubmitted(
            IntegrationEventFacts::id(PaymentRequestSubmitted::TYPE, $aggregateId, $event->occurredAt(), [$urssafId]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $urssafId,
        );
    }

    private static function paymentRequestRejected(PaymentRequestRejectedByUrssaf $event): PaymentRequestRejected
    {
        $aggregateId = $event->paymentRequestId()->value();
        $codes = array_map(static fn(SubmissionError $error): string => $error->code(), $event->errors());

        return new PaymentRequestRejected(
            IntegrationEventFacts::id(PaymentRequestRejected::TYPE, $aggregateId, $event->occurredAt(), $codes),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $codes,
        );
    }

    private static function statusChanged(DomainPaymentRequestStatusChanged $event): PaymentRequestStatusChanged
    {
        $aggregateId = $event->paymentRequestId()->value();
        $current = $event->current();
        $previous = $event->previous()?->rawCode();

        return new PaymentRequestStatusChanged(
            IntegrationEventFacts::id(PaymentRequestStatusChanged::TYPE, $aggregateId, $event->occurredAt(), [$previous, $current->rawCode()]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $previous,
            $current->rawCode(),
            $current->status()?->isFinal() === true,
            $current->status()?->isSettled() === true,
        );
    }
}
