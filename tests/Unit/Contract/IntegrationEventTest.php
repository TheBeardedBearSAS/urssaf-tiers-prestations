<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEventCodec;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestStatusChanged;

final class IntegrationEventTest extends TestCase
{
    public function testAnEventExposesItsTypeVersionAndProperties(): void
    {
        $event = self::event();

        static::assertSame('urssaf.payment_request.status_changed', $event->type());
        static::assertSame(1, $event->version());
        static::assertSame([
            'eventId' => 'event-1',
            'occurredAt' => '2022-03-01T10:00:00Z',
            'tenantId' => 'tenant-1',
            'aggregateId' => 'local-1',
            'externalReference' => 'invoice-42',
            'previousStatusCode' => '10',
            'statusCode' => '70',
            'final' => false,
            'settled' => true,
        ], $event->toArray());
    }

    public function testAnEventIsDecodedAsEncoded(): void
    {
        $event = self::event();

        static::assertEquals($event, IntegrationEventCodec::decode(IntegrationEventCodec::encode($event)));
    }

    public function testOnlyEncodedIntegrationEventsAreDecoded(): void
    {
        foreach ([serialize('text'), serialize(new stdClass())] as $data) {
            try {
                IntegrationEventCodec::decode($data);
                static::fail('The data should be refused.');
            } catch (InvalidArgumentException $failure) {
                static::assertSame('The data is not an encoded integration event.', $failure->getMessage());
            }
        }
    }

    public function testNoOtherClassIsInstantiatedWhileDecoding(): void
    {
        try {
            IntegrationEventCodec::decode(serialize(new WakeUpRecorder()));
        } catch (InvalidArgumentException $failure) {
            static::assertSame('The data is not an encoded integration event.', $failure->getMessage());
        }

        static::assertFalse(WakeUpRecorder::wokenUp());
    }

    private static function event(): PaymentRequestStatusChanged
    {
        return new PaymentRequestStatusChanged('event-1', '2022-03-01T10:00:00Z', 'tenant-1', 'local-1', 'invoice-42', '10', '70', false, true);
    }
}
