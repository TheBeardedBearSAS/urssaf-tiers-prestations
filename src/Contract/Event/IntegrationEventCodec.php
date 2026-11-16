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

use InvalidArgumentException;

/**
 * Encodes an integration event for the outbox and decodes it back (PHP serialization restricted to the event classes).
 */
final class IntegrationEventCodec
{
    /** @var list<class-string<IntegrationEvent>> */
    public const CLASSES = [
        CustomerRegistrationSubmitted::class,
        CustomerPairingChanged::class,
        CustomerRegistrationRejected::class,
        PaymentRequestSubmitted::class,
        PaymentRequestRejected::class,
        PaymentRequestStatusChanged::class,
    ];

    public static function encode(IntegrationEvent $event): string
    {
        return serialize($event);
    }

    /**
     * @throws InvalidArgumentException when the data is not an encoded integration event
     */
    public static function decode(string $data): IntegrationEvent
    {
        // @mago-expect analysis:mixed-assignment unserialize() is untyped: the instanceof check below narrows it.
        $event = unserialize($data, ['allowed_classes' => self::CLASSES]);

        return $event instanceof IntegrationEvent ? $event : throw new InvalidArgumentException('The data is not an encoded integration event.');
    }
}
