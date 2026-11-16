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
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class SubmitCustomerRegistrationHandlerTest extends TestCase
{
    public function testSubmitsTheClaimedRegistration(): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $harness->gateway->willRegister(Harness::urssafCustomerId(9));

        static::assertSame(ItemOutcome::Accepted, RegistrationSubmissionScenario::submit($harness));

        $stored = $harness->registrations->stored('reg-1');
        static::assertSame(RegistrationState::AwaitingPairing, $stored->status()->state());
        static::assertSame(Harness::urssafCustomerId(9)->value(), $stored->status()->urssafCustomerId()?->value());
        static::assertSame(RegistrationSubmissionScenario::LATER, $stored->status()->updatedAt()->toIso8601());
        static::assertSame(['tenant-1'], $harness->gateway->tenants());
        static::assertSame(
            ['claim:reg-1', 'register:reg-1', 'begin', 'save:reg-1', 'commit', 'publish:CustomerSubmittedToUrssaf'],
            $harness->journal->entries(),
        );
    }


    public function testDoesNothingWhenAnotherWorkerClaimedTheRegistration(): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $harness->registrations->refuseClaim(RegistrationId::fromString('reg-1'));

        static::assertSame(ItemOutcome::NotClaimed, RegistrationSubmissionScenario::submit($harness));
        static::assertSame([], $harness->journal->entries());
        static::assertSame(RegistrationState::Pending, $harness->registrations->stored('reg-1')->status()->state());
    }


    public function testDoesNothingForARegistrationThatIsNotPending(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::AwaitingPairing);

        static::assertSame(ItemOutcome::NotClaimed, RegistrationSubmissionScenario::submit($harness));
        static::assertSame([], $harness->journal->entries());
    }


    public function testMarksTheRegistrationRejectedOnABadRequest(): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $harness->gateway->willRegister(UrssafRequestRejected::withErrors(Operation::RegisterCustomer, 400, ApiErrors::of(
            ApiError::of('ERR_DGFIP_R2P_INCONNU', 'Unknown identity.', 'd'),
            ApiError::of('ERR_CONTACT', 'Invalid contact.', 'd'),
        )));

        static::assertSame(ItemOutcome::Rejected, RegistrationSubmissionScenario::submit($harness));

        $stored = $harness->registrations->stored('reg-1');
        static::assertSame(RegistrationState::Rejected, $stored->status()->state());
        static::assertSame(
            [['ERR_DGFIP_R2P_INCONNU', 'Unknown identity.'], ['ERR_CONTACT', 'Invalid contact.']],
            RegistrationSubmissionScenario::causes($stored),
        );
        static::assertSame(RegistrationSubmissionScenario::LATER, $stored->status()->updatedAt()->toIso8601());
        static::assertSame(['CustomerRegistrationRejected'], $harness->events->names());
    }


    public function testMarksTheCredentialsInvalid(): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $harness->gateway->willRegister(UrssafAuthenticationFailed::withStatus(401));

        static::assertSame(ItemOutcome::InvalidCredentials, RegistrationSubmissionScenario::submit($harness));
        static::assertSame(RegistrationState::InvalidCredentials, $harness->registrations->stored('reg-1')->status()->state());
        static::assertSame(['CustomerRegistrationCredentialsRejected'], $harness->events->names());
    }
}
