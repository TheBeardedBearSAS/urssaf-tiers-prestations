<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RefusalReason;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class SyncCustomerPairingHandlerTest extends TestCase
{
    public function testAppliesTheStatusesOfTheRegistrationsFollowingThePairing(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::AwaitingPairing);
        $harness->registration(2, RegistrationState::Blocked);
        $harness->registration(3, RegistrationState::MandateExpired);
        $harness->registration(4, RegistrationState::Registered);
        $harness->registration(5, RegistrationState::AwaitingPairing, Harness::OTHER_TENANT);
        $harness->registration(6, RegistrationState::Pending);
        $harness->clock->moveTo(PairingScenario::LATER);
        PairingScenario::willReport($harness, 1, 'APPAREILLAGE_VALIDE');
        PairingScenario::willReport($harness, 2, 'PARTICULIER_BLOQUE');
        PairingScenario::willReport($harness, 3, 'APPAREILLAGE_NF');

        $report = PairingScenario::sync($harness, null);

        static::assertEquals([
            new BatchItem('reg-1', ItemOutcome::Changed, ['APPAREILLAGE_VALIDE']),
            new BatchItem('reg-2', ItemOutcome::Unchanged, ['PARTICULIER_BLOQUE']),
            new BatchItem('reg-3', ItemOutcome::Changed, ['APPAREILLAGE_NF']),
        ], $report->items());
        $registered = $harness->registrations->stored('reg-1')->status();
        static::assertSame(RegistrationState::Registered, $registered->state());
        static::assertSame(PairingScenario::ACCEPTED_AT, $registered->pairingAcceptedAt()?->toIso8601());
        static::assertSame(PairingScenario::LATER, $registered->updatedAt()->toIso8601());
        static::assertSame(RegistrationState::Blocked, $harness->registrations->stored('reg-2')->status()->state());
        static::assertSame(PairingScenario::LATER, $harness->registrations->stored('reg-2')->status()->updatedAt()->toIso8601());
        static::assertSame(RegistrationState::AwaitingPairing, $harness->registrations->stored('reg-3')->status()->state());
        static::assertSame(['CustomerPairingValidated', 'CustomerPairingStatusChanged'], $harness->events->names());
        static::assertSame(3, $harness->transactions->committed());
        static::assertSame(['tenant-1', 'tenant-1', 'tenant-1'], $harness->gateway->tenants());
        static::assertSame([
            'status:' . Harness::urssafCustomerId(1)->value(), 'begin', 'save:reg-1', 'commit', 'publish:CustomerPairingValidated',
            'status:' . Harness::urssafCustomerId(2)->value(), 'begin', 'save:reg-2', 'commit',
            'status:' . Harness::urssafCustomerId(3)->value(), 'begin', 'save:reg-3', 'commit', 'publish:CustomerPairingStatusChanged',
        ], $harness->journal->entries());
    }


    public function testLimitsTheNumberOfRegistrationsWithoutStarvingBlockedOrExpiredOnes(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Blocked);
        $harness->registration(2, RegistrationState::AwaitingPairing);
        $harness->registration(3, RegistrationState::MandateExpired);
        PairingScenario::willReport($harness, 1, 'PARTICULIER_BLOQUE');
        PairingScenario::willReport($harness, 2, 'APPAREILLAGE_EC');

        $report = PairingScenario::sync($harness, 2);

        static::assertSame(['reg-1', 'reg-2'], array_map(static fn(BatchItem $item): string => $item->subject, $report->items()));
        static::assertSame(['reg-1'], PairingScenario::subjects(PairingScenario::sync($harness, 1)));
    }


    public function testLeavesUnknownStatusesUntouched(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::AwaitingPairing);
        $harness->gateway->willReportStatus(Harness::urssafCustomerId(1), CustomerTransmissionStatus::blocked(Harness::urssafCustomerId(1), 'NEW_CODE', 'd'));

        static::assertEquals([new BatchItem('reg-1', ItemOutcome::UnknownStatus, ['NEW_CODE'])], PairingScenario::sync($harness, null)->items());
        static::assertSame(0, $harness->transactions->committed());
    }


    public function testRejectsARegistrationNoLongerKnownByUrssaf(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::AwaitingPairing);
        $harness->gateway->willReportStatus(Harness::urssafCustomerId(1), PairingScenario::rejected('ERR_RECHERCHE_PARTICULIER', 'No registration.', RefusalReason::NoActiveRegistration));

        static::assertEquals([new BatchItem('reg-1', ItemOutcome::Rejected, ['ERR_RECHERCHE_PARTICULIER'])], PairingScenario::sync($harness, null)->items());
        $status = $harness->registrations->stored('reg-1')->status();
        static::assertSame(RegistrationState::Rejected, $status->state());
        static::assertSame('ERR_RECHERCHE_PARTICULIER', $status->lastErrors()[0]->code());
        static::assertSame('No registration.', $status->lastErrors()[0]->message());
        static::assertSame(Harness::NOW, $status->updatedAt()->toIso8601());
        static::assertSame(['CustomerRegistrationRejected'], $harness->events->names());
    }


    public function testReportsOtherBadRequests(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::AwaitingPairing);
        $harness->registration(2, RegistrationState::AwaitingPairing);
        $harness->gateway->willReportStatus(Harness::urssafCustomerId(1), PairingScenario::rejected('ERR_CRITERE_RECHERCHE_VIDE', 'm'));
        PairingScenario::willReport($harness, 2, 'APPAREILLAGE_EC');

        static::assertEquals([
            new BatchItem('reg-1', ItemOutcome::Failed, ['ERR_CRITERE_RECHERCHE_VIDE']),
            new BatchItem('reg-2', ItemOutcome::Unchanged, ['APPAREILLAGE_EC']),
        ], PairingScenario::sync($harness, null)->items());
        static::assertSame(RegistrationState::AwaitingPairing, $harness->registrations->stored('reg-1')->status()->state());
    }


    public function testTemporaryFailuresStopTheRunButKeepWhatWasDone(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::AwaitingPairing);
        $harness->registration(2, RegistrationState::AwaitingPairing);
        PairingScenario::willReport($harness, 1, 'APPAREILLAGE_VALIDE');
        $unavailable = UrssafUnavailable::withStatus(Operation::GetCustomerStatus, 503, null);
        $harness->gateway->willReportStatus(Harness::urssafCustomerId(2), $unavailable);

        try {
            PairingScenario::sync($harness, null);
            static::fail('Expected the failure to propagate.');
        } catch (UrssafUnavailable $exception) {
            static::assertSame($unavailable, $exception);
        }
        static::assertSame(RegistrationState::Registered, $harness->registrations->stored('reg-1')->status()->state());
        static::assertSame(['CustomerPairingValidated'], $harness->events->names());
    }
}
