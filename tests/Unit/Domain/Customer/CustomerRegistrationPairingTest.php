<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingRecheckRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingValidated;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;

final class CustomerRegistrationPairingTest extends TestCase
{
    private const LATER = '2026-11-12T10:00:00Z';

    public function testValidatedPairingRegistersTheCustomer(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::AwaitingPairing);
        $registration->applyPairingStatus(PairingStatus::Validated, CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT), CustomerFixtures::at(self::LATER));

        $status = $registration->status();
        static::assertSame(RegistrationState::Registered, $status->state());
        static::assertSame(CustomerFixtures::ACCEPTED_AT, $status->pairingAcceptedAt()?->toIso8601());
        static::assertSame(self::LATER, $status->updatedAt()->toIso8601());
        static::assertSame(CustomerFixtures::URSSAF_ID, $status->urssafCustomerId()?->value());
        $events = $registration->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(CustomerPairingValidated::class, $event);
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame(CustomerFixtures::ACCEPTED_AT, $event->pairingAcceptedAt()->toIso8601());
        static::assertSame('2026-11-12T10:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));
    }

    public function testValidatedPairingRequiresTheAcceptanceDate(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Registered);

        $exception = CustomerFixtures::assertViolationCode(
            'customer_registration.pairing_acceptance_required',
            static fn() => $registration->applyPairingStatus(PairingStatus::Validated, null, CustomerFixtures::now()),
        );

        static::assertSame('A validated pairing requires the pairing acceptance date.', $exception->getMessage());
    }

    public function testConfirmedPairingOnlyRefreshesTheUpdateDate(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Registered);
        $registration->applyPairingStatus(PairingStatus::Validated, CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT), CustomerFixtures::at(self::LATER));

        static::assertSame([], $registration->releaseEvents());
        static::assertSame(RegistrationState::Registered, $registration->status()->state());
        static::assertSame(self::LATER, $registration->status()->updatedAt()->toIso8601());
    }

    public function testNewAcceptanceDateIsRecorded(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Registered);
        $registration->applyPairingStatus(PairingStatus::Validated, CustomerFixtures::at('2026-11-05T00:00:00Z'), CustomerFixtures::at(self::LATER));

        static::assertSame('2026-11-05T00:00:00Z', $registration->status()->pairingAcceptedAt()?->toIso8601());
        static::assertCount(1, $registration->releaseEvents());
    }

    public function testRevalidationAfterSuspensionWithTheSameDateIsRecorded(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Blocked);
        static::assertSame(CustomerFixtures::ACCEPTED_AT, $registration->status()->pairingAcceptedAt()?->toIso8601());
        $registration->applyPairingStatus(PairingStatus::Validated, CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT), CustomerFixtures::now());

        static::assertSame(RegistrationState::Registered, $registration->status()->state());
        static::assertCount(1, $registration->releaseEvents());
    }

    /**
     * @return iterable<string, array{RegistrationState, PairingStatus, RegistrationState}>
     */
    public static function provideNonTransmittingStatuses(): iterable
    {
        foreach ([RegistrationState::AwaitingPairing, RegistrationState::Registered, RegistrationState::Blocked, RegistrationState::MandateExpired] as $from) {
            foreach ([PairingStatus::InProgress, PairingStatus::NotFinalised, PairingStatus::CustomerBlocked, PairingStatus::MandateExpired] as $pairingStatus) {
                $to = $pairingStatus->registrationState();

                yield $from->value . ' + ' . $pairingStatus->value => [$from, $pairingStatus, $to];
            }
        }
    }

    #[DataProvider('provideNonTransmittingStatuses')]
    public function testNonTransmittingStatuses(RegistrationState $from, PairingStatus $pairingStatus, RegistrationState $to): void
    {
        $registration = CustomerFixtures::registrationIn($from);
        $registration->applyPairingStatus($pairingStatus, CustomerFixtures::at('2026-11-09T00:00:00Z'), CustomerFixtures::at(self::LATER));

        static::assertSame($to, $registration->status()->state());
        static::assertSame(self::LATER, $registration->status()->updatedAt()->toIso8601());
        static::assertSame(
            $from === RegistrationState::AwaitingPairing ? null : CustomerFixtures::ACCEPTED_AT,
            $registration->status()->pairingAcceptedAt()?->toIso8601(),
        );
        $events = $registration->releaseEvents();
        if ($to === $from) {
            static::assertSame([], $events);

            return;
        }
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(CustomerPairingStatusChanged::class, $event);
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame($pairingStatus, $event->pairingStatus());
        static::assertSame($from, $event->previousState());
        static::assertSame($to, $event->newState());
        static::assertSame('2026-11-12T10:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));
    }

    public function testARegisteredCustomerIsSentBackToPairingConfirmation(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Registered);
        $registration->requestPairingRecheck(CustomerFixtures::at(self::LATER));

        $status = $registration->status();
        static::assertSame(RegistrationState::AwaitingPairing, $status->state());
        static::assertSame(self::LATER, $status->updatedAt()->toIso8601());
        static::assertSame(CustomerFixtures::ACCEPTED_AT, $status->pairingAcceptedAt()?->toIso8601());
        static::assertSame(CustomerFixtures::URSSAF_ID, $status->urssafCustomerId()?->value());
        $events = $registration->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(CustomerPairingRecheckRequested::class, $event);
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('2026-11-12T10:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));
    }

    /**
     * @return iterable<string, array{RegistrationState}>
     */
    public static function provideStatesNotRechecked(): iterable
    {
        foreach (RegistrationState::cases() as $state) {
            if ($state === RegistrationState::Registered) {
                continue;
            }

            yield $state->value => [$state];
        }
    }

    #[DataProvider('provideStatesNotRechecked')]
    public function testOnlyARegisteredCustomerIsSentBackToPairingConfirmation(RegistrationState $state): void
    {
        $registration = CustomerFixtures::registrationIn($state);
        $before = $registration->status();
        $registration->requestPairingRecheck(CustomerFixtures::at(self::LATER));

        static::assertSame($before, $registration->status());
        static::assertSame([], $registration->releaseEvents());
    }
}
