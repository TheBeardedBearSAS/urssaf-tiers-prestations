<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake\StaticCredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayStatusTest extends TestCase
{
    private const CUSTOMER = 'af0be430-908a-11eb-a8b3-0242ac130003';

    public function testItReadsTheTransmissionStatusOfACustomer(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('customer_status_pairing_valid'));

        $status = $stack->gateway()->transmissionStatus(Stacks::tenant(), UrssafCustomerId::fromString(self::CUSTOMER));

        static::assertTrue($status->isTransmissionAllowed());
        static::assertSame('2023-03-15T13:30:05Z', $status->pairingAcceptedAt()?->toIso8601());
        $request = $stack->lastRequest();
        static::assertSame('GET', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/atp/v1/tiersPrestations/particulier?idClient=' . self::CUSTOMER,
            (string) $request->getUri(),
        );
        static::assertSame(['tenant-1:get_customer_status'], $stack->rateLimiter->acquired());
    }

    public function testTheCustomerIdIsSentInLowerCase(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('customer_status_pairing_in_progress'));

        $stack->gateway()->transmissionStatus(Stacks::tenant(), UrssafCustomerId::fromString(strtoupper(self::CUSTOMER)));

        static::assertSame('idClient=' . self::CUSTOMER, $stack->lastRequest()->getUri()->getQuery());
    }

    public function testItAuthenticatesWithTheCredentialsOfTheTenant(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(200, Fixtures::load('customer_status_mandate_expired'));

        $status = $stack->gateway()->transmissionStatus(Stacks::tenant(), UrssafCustomerId::fromString(self::CUSTOMER));

        static::assertFalse($status->isTransmissionAllowed());
        static::assertSame('MANDAT_ECHU', $status->code());
        $form = [];
        parse_str((string) $stack->requests()[0]->getBody(), $form);
        static::assertSame(StaticCredentialsProvider::CLIENT_ID, $form['client_id'] ?? null);
    }

    public function testAnUnknownRegistrationIsARejection(): void
    {
        $stack = Stacks::sandbox();
        $stack->respondToken();
        $stack->respond(400, '{"code":"ERR_RECHERCHE_PARTICULIER","message":"Aucun particulier","description":"Aucun"}');

        try {
            $stack->gateway()->transmissionStatus(Stacks::tenant(), UrssafCustomerId::fromString(self::CUSTOMER));
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $exception) {
            static::assertTrue($exception->hasErrorCode('ERR_RECHERCHE_PARTICULIER'));
        }
    }
}
