<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

final class CustomerTransmissionStatusTest extends TestCase
{
    public function testAllowed(): void
    {
        $customerId = UrssafCustomerId::fromString('af0be430-908a-11eb-a8b3-0242ac130003');
        $acceptedAt = Instant::fromIso8601('2022-01-10T09:00:00Z');

        $status = CustomerTransmissionStatus::allowed(
            $customerId,
            'APPAREILLAGE_VALIDE',
            '10/01/2022 10:00:00',
            $acceptedAt,
        );

        static::assertSame($customerId, $status->customerId());
        static::assertSame('APPAREILLAGE_VALIDE', $status->code());
        static::assertSame(PairingStatus::Validated, $status->pairingStatus());
        static::assertTrue($status->isTransmissionAllowed());
        static::assertSame('10/01/2022 10:00:00', $status->description());
        static::assertSame($acceptedAt, $status->pairingAcceptedAt());
    }

    public function testBlocked(): void
    {
        $customerId = UrssafCustomerId::fromString('af0be430-908a-11eb-a8b3-0242ac130003');

        $status = CustomerTransmissionStatus::blocked($customerId, 'APPAREILLAGE_EC', 'En cours');

        static::assertSame($customerId, $status->customerId());
        static::assertSame('APPAREILLAGE_EC', $status->code());
        static::assertSame(PairingStatus::InProgress, $status->pairingStatus());
        static::assertFalse($status->isTransmissionAllowed());
        static::assertSame('En cours', $status->description());
        static::assertNull($status->pairingAcceptedAt());
    }

    public function testAnUnknownCodeHasNoPairingStatus(): void
    {
        $status = CustomerTransmissionStatus::blocked(UrssafCustomerId::fromString('af0be430-908a-11eb-a8b3-0242ac130003'), 'NEW_CODE', '');

        static::assertNull($status->pairingStatus());
    }

    public function testBlockedRequiresACode(): void
    {
        $this->expectExceptionObject(InvariantViolation::because(
            'customer_transmission_status.empty_code',
            'A blocked transmission status must carry a status code.',
        ));

        CustomerTransmissionStatus::blocked(UrssafCustomerId::fromString('af0be430-908a-11eb-a8b3-0242ac130003'), '', 'x');
    }
}
