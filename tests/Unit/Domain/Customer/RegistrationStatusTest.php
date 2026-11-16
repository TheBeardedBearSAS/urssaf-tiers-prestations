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

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class RegistrationStatusTest extends TestCase
{
    public function testAccessors(): void
    {
        $status = new RegistrationStatus(
            RegistrationState::Registered,
            CustomerFixtures::urssafId(),
            CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT),
            [RejectionCause::of('CODE_A', 'First.'), RejectionCause::of('CODE_B', 'Second.')],
            CustomerFixtures::now(),
            0,
            null,
        );

        static::assertSame(RegistrationState::Registered, $status->state());
        static::assertSame(CustomerFixtures::URSSAF_ID, $status->urssafCustomerId()?->value());
        static::assertSame(CustomerFixtures::ACCEPTED_AT, $status->pairingAcceptedAt()?->toIso8601());
        static::assertSame(CustomerFixtures::NOW, $status->updatedAt()->toIso8601());
        static::assertSame(
            [['CODE_A', 'First.'], ['CODE_B', 'Second.']],
            array_map(static fn(RejectionCause $cause): array => [$cause->code(), $cause->message()], $status->lastErrors()),
        );
    }

    public function testEmptyStatus(): void
    {
        $status = new RegistrationStatus(RegistrationState::Pending, null, null, [], CustomerFixtures::now(), 0, null);

        static::assertNull($status->urssafCustomerId());
        static::assertNull($status->pairingAcceptedAt());
        static::assertSame([], $status->lastErrors());
    }

    /**
     * @return iterable<string, array{RegistrationState}>
     */
    public static function providePairingStates(): iterable
    {
        yield 'awaiting pairing' => [RegistrationState::AwaitingPairing];
        yield 'registered' => [RegistrationState::Registered];
        yield 'blocked' => [RegistrationState::Blocked];
        yield 'mandate expired' => [RegistrationState::MandateExpired];
    }

    #[DataProvider('providePairingStates')]
    public function testPairingStatesRequireTheUrssafId(RegistrationState $state): void
    {
        $exception = CustomerFixtures::assertViolationCode(
            'customer_registration.urssaf_customer_id_required',
            static fn(): RegistrationStatus => new RegistrationStatus($state, null, CustomerFixtures::now(), [], CustomerFixtures::now(), 0, null),
        );

        static::assertSame(\sprintf('A customer registration in state "%s" requires the URSSAF customer id.', $state->value), $exception->getMessage());
    }

    public function testRegisteredRequiresTheAcceptanceDate(): void
    {
        static::assertSame(
            RegistrationState::Blocked,
            (new RegistrationStatus(RegistrationState::Blocked, CustomerFixtures::urssafId(), null, [], CustomerFixtures::now(), 0, null))->state(),
        );

        $exception = CustomerFixtures::assertViolationCode(
            'customer_registration.pairing_acceptance_required',
            static fn(): RegistrationStatus => new RegistrationStatus(RegistrationState::Registered, CustomerFixtures::urssafId(), null, [], CustomerFixtures::now(), 0, null),
        );

        static::assertSame('A registered customer requires the pairing acceptance date.', $exception->getMessage());
    }

    public function testHasPairingAcceptedAt(): void
    {
        $accepted = CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT);
        $status = new RegistrationStatus(RegistrationState::Registered, CustomerFixtures::urssafId(), $accepted, [], CustomerFixtures::now(), 0, null);
        $empty = new RegistrationStatus(RegistrationState::Pending, null, null, [], CustomerFixtures::now(), 0, null);

        static::assertTrue($status->hasPairingAcceptedAt($accepted));
        static::assertFalse($status->hasPairingAcceptedAt(CustomerFixtures::at('2026-11-05T00:00:00Z')));
        static::assertFalse($empty->hasPairingAcceptedAt($accepted));
    }

    public function testTransitionKeepsOrReplacesValues(): void
    {
        $cause = RejectionCause::of('CODE', 'Message.');
        $status = new RegistrationStatus(RegistrationState::Registered, CustomerFixtures::urssafId(), CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT), [$cause], CustomerFixtures::at('2026-11-02T00:00:00Z'), 0, null);

        $kept = $status->transitionTo(RegistrationState::Blocked, CustomerFixtures::now(), null, null, null);
        static::assertSame(RegistrationState::Blocked, $kept->state());
        static::assertSame(CustomerFixtures::URSSAF_ID, $kept->urssafCustomerId()?->value());
        static::assertSame(CustomerFixtures::ACCEPTED_AT, $kept->pairingAcceptedAt()?->toIso8601());
        static::assertSame([$cause->code()], array_map(static fn(RejectionCause $c): string => $c->code(), $kept->lastErrors()));
        static::assertSame(CustomerFixtures::NOW, $kept->updatedAt()->toIso8601());
        static::assertSame(RegistrationState::Registered, $status->state());

        $replaced = $status->transitionTo(
            RegistrationState::Registered,
            CustomerFixtures::now(),
            UrssafCustomerId::fromString('6a56a628-b09e-8707-787e-10f218a2d550'),
            CustomerFixtures::at('2026-11-05T00:00:00Z'),
            [],
        );
        static::assertSame('6a56a628-b09e-8707-787e-10f218a2d550', $replaced->urssafCustomerId()?->value());
        static::assertSame('2026-11-05T00:00:00Z', $replaced->pairingAcceptedAt()?->toIso8601());
        static::assertSame([], $replaced->lastErrors());
    }

    public function testForbiddenTransition(): void
    {
        $status = new RegistrationStatus(RegistrationState::Pending, null, null, [], CustomerFixtures::now(), 0, null);

        $this->expectExceptionObject(InvalidStateTransition::between('customer_registration', 'pending', 'registered'));

        $status->transitionTo(RegistrationState::Registered, CustomerFixtures::now(), null, null, null);
    }

    public function testAttemptsAreCountedByEachSubmissionStartAndKeptByTransitions(): void
    {
        $pending = new RegistrationStatus(RegistrationState::Pending, null, null, [], CustomerFixtures::at('2026-11-01T00:00:00Z'), 0, null);
        $first = CustomerFixtures::at('2026-11-02T00:00:00Z');

        $submitting = $pending->submissionStarted($first);
        static::assertSame(RegistrationState::Submitting, $submitting->state());
        static::assertSame(1, $submitting->attempts());
        static::assertSame($first->toIso8601(), $submitting->lastAttemptAt()?->toIso8601());
        static::assertSame($first->toIso8601(), $submitting->updatedAt()->toIso8601());
        static::assertSame(0, $pending->attempts());
        static::assertNull($pending->lastAttemptAt());

        $failed = $submitting->transitionTo(RegistrationState::Error, CustomerFixtures::now(), null, null, [CustomerFixtures::cause()]);
        $again = $failed->transitionTo(RegistrationState::Pending, CustomerFixtures::now(), null, null, null)
            ->submissionStarted(CustomerFixtures::now());
        static::assertSame(1, $failed->attempts());
        static::assertSame($first->toIso8601(), $failed->lastAttemptAt()?->toIso8601());
        static::assertSame(2, $again->attempts());
        static::assertSame(CustomerFixtures::NOW, $again->lastAttemptAt()?->toIso8601());
        static::assertSame([CustomerFixtures::cause()->code()], array_map(static fn(RejectionCause $c): string => $c->code(), $again->lastErrors()));
    }

    public function testASubmissionStartsOnlyFromPending(): void
    {
        $status = new RegistrationStatus(RegistrationState::Error, null, null, [], CustomerFixtures::now(), 1, CustomerFixtures::now());

        $this->expectExceptionObject(InvalidStateTransition::between('customer_registration', 'error', 'submitting'));

        $status->submissionStarted(CustomerFixtures::now());
    }

    public function testTheAttemptsLeftAreCountedAgainstTheMaximum(): void
    {
        $status = new RegistrationStatus(RegistrationState::Error, null, null, [], CustomerFixtures::now(), 2, CustomerFixtures::now());

        static::assertTrue($status->hasAttemptsLeft(3));
        static::assertFalse($status->hasAttemptsLeft(2));
    }

    public function testTheAttemptCountIsNotNegative(): void
    {
        static::assertSame(0, (new RegistrationStatus(RegistrationState::Pending, null, null, [], CustomerFixtures::now(), 0, null))->attempts());

        $exception = CustomerFixtures::assertViolationCode(
            'customer_registration.negative_attempts',
            static fn(): RegistrationStatus => new RegistrationStatus(RegistrationState::Pending, null, null, [], CustomerFixtures::now(), -1, null),
        );

        static::assertSame(['attempts' => -1], $exception->params());
        static::assertSame('The number of submission attempts must not be negative.', $exception->getMessage());
    }

    public function testTheLastAttemptIsStartedBeforeAnAgeWhenItsStartPlusTheAgeIsNotAfterNow(): void
    {
        $started = new RegistrationStatus(RegistrationState::Submitting, null, null, [], CustomerFixtures::now(), 1, CustomerFixtures::at('2026-11-11T09:45:00Z'));
        $never = new RegistrationStatus(RegistrationState::Pending, null, null, [], CustomerFixtures::now(), 0, null);
        $age = new DateInterval('PT15M');

        static::assertTrue($started->isLastAttemptStartedBefore(CustomerFixtures::at('2026-11-11T10:00:00Z'), $age));
        static::assertTrue($started->isLastAttemptStartedBefore(CustomerFixtures::at('2026-11-11T10:00:01Z'), $age));
        static::assertFalse($started->isLastAttemptStartedBefore(CustomerFixtures::at('2026-11-11T09:59:59Z'), $age));
        static::assertFalse($never->isLastAttemptStartedBefore(CustomerFixtures::at('2026-11-11T10:00:00Z'), $age));
    }

    public function testTouchingOnlyMovesTheUpdateInstant(): void
    {
        $cause = CustomerFixtures::cause();
        $status = new RegistrationStatus(RegistrationState::Rejected, CustomerFixtures::urssafId(), CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT), [$cause], CustomerFixtures::at('2026-11-02T00:00:00Z'), 3, CustomerFixtures::at('2026-11-01T00:00:00Z'));

        $touched = $status->touched(CustomerFixtures::now());

        static::assertSame(RegistrationState::Rejected, $touched->state());
        static::assertSame(CustomerFixtures::URSSAF_ID, $touched->urssafCustomerId()?->value());
        static::assertSame(CustomerFixtures::ACCEPTED_AT, $touched->pairingAcceptedAt()?->toIso8601());
        static::assertSame([$cause], $touched->lastErrors());
        static::assertSame(CustomerFixtures::NOW, $touched->updatedAt()->toIso8601());
        static::assertSame(3, $touched->attempts());
        static::assertSame('2026-11-01T00:00:00Z', $touched->lastAttemptAt()?->toIso8601());
    }
}
