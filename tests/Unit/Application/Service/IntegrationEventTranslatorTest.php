<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\IntegrationEventTranslator;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\CustomerPairingChanged;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\CustomerRegistrationRejected;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\CustomerRegistrationSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\PaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\DomainEventSamples as Samples;

final class IntegrationEventTranslatorTest extends TestCase
{
    private const COMMON = ['occurredAt' => Samples::AT, 'tenantId' => 'tenant-1'];

    public function testTheCustomerEventsAreTranslated(): void
    {
        $customer = ['aggregateId' => 'reg-1', 'externalReference' => 'customer-1'];

        self::assertTranslated(CustomerRegistrationSubmitted::class, [...$customer, 'urssafCustomerId' => Samples::URSSAF_CUSTOMER_ID], Samples::customerSubmitted());
        self::assertTranslated(CustomerPairingChanged::class, [...$customer, 'pairingStatus' => 'APPAREILLAGE_VALIDE', 'state' => 'registered', 'registered' => true], Samples::pairingValidated());
        self::assertTranslated(CustomerPairingChanged::class, [...$customer, 'pairingStatus' => 'PARTICULIER_BLOQUE', 'state' => 'blocked', 'registered' => false], Samples::pairingChanged(PairingStatus::CustomerBlocked, RegistrationState::Blocked));
        self::assertTranslated(CustomerPairingChanged::class, [...$customer, 'pairingStatus' => 'APPAREILLAGE_VALIDE', 'state' => 'registered', 'registered' => true], Samples::pairingChanged(PairingStatus::Validated, RegistrationState::Registered));
        self::assertTranslated(CustomerRegistrationRejected::class, [...$customer, 'errorCodes' => ['ERR_DGFIP_R2P_INCONNU', 'ERR_PARTICULIER_INCONNU']], Samples::customerRejected());
    }

    public function testThePaymentRequestEventsAreTranslated(): void
    {
        $request = ['aggregateId' => 'local-1', 'externalReference' => 'invoice-42'];

        self::assertTranslated(PaymentRequestSubmitted::class, [...$request, 'urssafPaymentRequestId' => 'urssaf-1'], Samples::paymentRequestSubmitted());
        self::assertTranslated(PaymentRequestRejected::class, [...$request, 'errorCodes' => ['ERR_DATE_FUTUR']], Samples::paymentRequestRejected());
        self::assertTranslated(PaymentRequestStatusChanged::class, [...$request, 'previousStatusCode' => null, 'statusCode' => '10', 'final' => false, 'settled' => false], Samples::statusChanged(null, PaymentRequestStatus::Integrated));
        self::assertTranslated(PaymentRequestStatusChanged::class, [...$request, 'previousStatusCode' => '30', 'statusCode' => '70', 'final' => false, 'settled' => true], Samples::statusChanged('30', PaymentRequestStatus::Paid));
        self::assertTranslated(PaymentRequestStatusChanged::class, [...$request, 'previousStatusCode' => '20', 'statusCode' => '40', 'final' => true, 'settled' => false], Samples::statusChanged('20', PaymentRequestStatus::Refused));
        self::assertTranslated(PaymentRequestStatusChanged::class, [...$request, 'previousStatusCode' => '20', 'statusCode' => '999', 'final' => false, 'settled' => false], Samples::statusChanged('20', '999'));
    }

    public function testTheOtherEventsHaveNoIntegrationEvent(): void
    {
        $translated = IntegrationEventTranslator::translate(Samples::submissionStarted(), Samples::customerSubmitted(), Samples::submissionStarted(), Samples::paymentRequestSubmitted());

        static::assertCount(2, $translated);
        static::assertInstanceOf(CustomerRegistrationSubmitted::class, $translated[0]);
        static::assertInstanceOf(PaymentRequestSubmitted::class, $translated[1]);
    }

    public function testTheSameFactAlwaysHasTheSameIdAndAnotherFactAnotherId(): void
    {
        $ids = array_map(static fn(IntegrationEvent $event): string => $event->eventId, IntegrationEventTranslator::translate(
            Samples::statusChanged('10', PaymentRequestStatus::Paid),
            Samples::statusChanged('10', PaymentRequestStatus::Paid),
            Samples::statusChanged('20', PaymentRequestStatus::Paid),
            Samples::statusChanged('10', PaymentRequestStatus::Recovered),
            Samples::statusChanged('10', PaymentRequestStatus::Paid, 'local-2'),
            Samples::paymentRequestSubmitted(),
        ));

        static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ids[0]);
        static::assertSame($ids[0], $ids[1]);
        static::assertCount(5, array_unique($ids));
    }

    public function testTheEventIdsAreStableAcrossVersions(): void
    {
        // The hosts deduplicate on these ids: changing how they are derived is a breaking change.
        $ids = array_map(static fn(IntegrationEvent $event): string => $event->eventId, IntegrationEventTranslator::translate(
            Samples::customerSubmitted(),
            Samples::pairingValidated(),
            Samples::pairingChanged(PairingStatus::CustomerBlocked, RegistrationState::Blocked),
            Samples::customerRejected(),
            Samples::paymentRequestSubmitted(),
            Samples::paymentRequestRejected(),
            Samples::statusChanged('30', PaymentRequestStatus::Paid),
        ));

        static::assertSame([
            '0ec8ec07-3168-3add-063b-4180ea7a2080',
            '414e4afb-078b-d09c-f897-64ca75214ca1',
            '414a942a-354c-0f18-6e72-cc5ae68467e3',
            'f25899b0-4d50-9b16-65ed-3a7757067bc3',
            '274ec947-24e4-6456-d642-27a1e59dee1f',
            '946776f9-9a6b-25ed-7701-b57927d1048d',
            '431db592-01b7-63cc-c42d-0979d0669487',
        ], $ids);
    }

    /**
     * @param class-string<IntegrationEvent> $class
     * @param array<string, mixed>           $expected the properties of the event, but the common ones and the id
     */
    private static function assertTranslated(string $class, array $expected, DomainEvent $event): void
    {
        $translated = IntegrationEventTranslator::translate($event);

        static::assertCount(1, $translated);
        static::assertInstanceOf($class, $translated[0]);
        $properties = $translated[0]->toArray();
        unset($properties['eventId']);
        static::assertSame([...self::COMMON, ...$expected], [...array_intersect_key($properties, self::COMMON), ...array_diff_key($properties, self::COMMON)]);
    }
}
