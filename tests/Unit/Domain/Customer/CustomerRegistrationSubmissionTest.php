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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationCredentialsRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRetried;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerSubmissionStarted;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerSubmittedToUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;

final class CustomerRegistrationSubmissionTest extends TestCase
{
    /**
     * @param list<DomainEvent> $events
     *
     * @return DomainEvent the single event
     */
    private static function single(array $events): DomainEvent
    {
        static::assertCount(1, $events);

        return $events[0];
    }

    public function testStartSubmission(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Pending);
        $registration->startSubmission(CustomerFixtures::now());

        static::assertSame(RegistrationState::Submitting, $registration->status()->state());
        static::assertSame(CustomerFixtures::NOW, $registration->status()->updatedAt()->toIso8601());
        $event = self::single($registration->releaseEvents());
        static::assertInstanceOf(CustomerSubmissionStarted::class, $event);
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('2026-11-11T10:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));
    }

    public function testMarkSubmitted(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Error);
        $registration->retry(CustomerFixtures::now());
        $registration->startSubmission(CustomerFixtures::now());
        $registration->releaseEvents();
        $registration->markSubmitted(CustomerFixtures::urssafId(), CustomerFixtures::at('2026-11-11T11:00:00Z'));

        $status = $registration->status();
        static::assertSame(RegistrationState::AwaitingPairing, $status->state());
        static::assertSame(CustomerFixtures::URSSAF_ID, $status->urssafCustomerId()?->value());
        static::assertSame([], $status->lastErrors());
        static::assertSame('2026-11-11T11:00:00Z', $status->updatedAt()->toIso8601());
        $event = self::single($registration->releaseEvents());
        static::assertInstanceOf(CustomerSubmittedToUrssaf::class, $event);
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame(CustomerFixtures::URSSAF_ID, $event->urssafCustomerId()->value());
        static::assertSame('2026-11-11T11:00:00Z', $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testMarkRejected(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Submitting);
        $cause = RejectionCause::of('ERR_COMPTE_BLOQUE', 'Blocked.');
        $registration->markRejected([$cause], CustomerFixtures::now());

        static::assertSame(RegistrationState::Rejected, $registration->status()->state());
        static::assertSame('ERR_COMPTE_BLOQUE', $registration->status()->lastErrors()[0]->code());
        $event = self::single($registration->releaseEvents());
        static::assertInstanceOf(CustomerRegistrationRejected::class, $event);
        static::assertSame([$cause], $event->causes());
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('2026-11-11T10:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));
    }

    public function testMarkFailedThenRetryKeepsTheErrors(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Submitting);
        $cause = RejectionCause::of('ERREUR_TECHNIQUE', 'Technical error.');
        $registration->markFailed([$cause], CustomerFixtures::now());

        static::assertSame(RegistrationState::Error, $registration->status()->state());
        $event = self::single($registration->releaseEvents());
        static::assertInstanceOf(CustomerRegistrationFailed::class, $event);
        static::assertSame([$cause], $event->causes());
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('2026-11-11T10:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));

        $registration->retry(CustomerFixtures::at('2026-11-12T00:00:00Z'));
        static::assertSame(RegistrationState::Pending, $registration->status()->state());
        static::assertSame('ERREUR_TECHNIQUE', $registration->status()->lastErrors()[0]->code());
        $retried = self::single($registration->releaseEvents());
        static::assertInstanceOf(CustomerRegistrationRetried::class, $retried);
        static::assertSame('reg-1', $retried->registrationId()->value());
        static::assertSame('tenant-1', $retried->tenantId()->value());
        static::assertSame('2026-11-12T00:00:00Z', $retried->occurredAt()->format('Y-m-d\TH:i:s\Z'));

        $registration->startSubmission(CustomerFixtures::now());
        static::assertSame('ERREUR_TECHNIQUE', $registration->status()->lastErrors()[0]->code());
    }

    public function testMarkInvalidCredentialsClearsTheErrors(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Error);
        $registration->retry(CustomerFixtures::now());
        $registration->startSubmission(CustomerFixtures::now());
        $registration->releaseEvents();
        $registration->markInvalidCredentials(CustomerFixtures::now());

        static::assertSame(RegistrationState::InvalidCredentials, $registration->status()->state());
        static::assertSame([], $registration->status()->lastErrors());
        $event = self::single($registration->releaseEvents());
        static::assertInstanceOf(CustomerRegistrationCredentialsRejected::class, $event);
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('2026-11-11T10:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));
    }
}
