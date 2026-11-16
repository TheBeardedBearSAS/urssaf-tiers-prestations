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
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleRegistrations;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleRegistrationsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class ReconcileStaleRegistrationsHandlerTest extends TestCase
{
    private const LATER = '2022-03-01T00:15:00Z';

    public function testLeavesTheStaleSubmissionsInErrorForManualReview(): void
    {
        $harness = self::harness();

        $report = self::reconcile($harness, null);

        static::assertEquals([
            new BatchItem('reg-1', ItemOutcome::ManualReview, [SubmitCustomerRegistrationHandler::OUTCOME_UNKNOWN]),
            new BatchItem('reg-2', ItemOutcome::ManualReview, [SubmitCustomerRegistrationHandler::OUTCOME_UNKNOWN]),
        ], $report->items());
        $status = $harness->registrations->stored('reg-2')->status();
        static::assertSame(RegistrationState::Error, $status->state());
        static::assertSame(self::LATER, $status->updatedAt()->toIso8601());
        static::assertSame(SubmitCustomerRegistrationHandler::OUTCOME_UNKNOWN, $status->lastErrors()[0]->code());
        static::assertSame(
            'The registration (method 010) was not confirmed: URSSAF may have registered the customer.',
            $status->lastErrors()[0]->message(),
        );
        static::assertFalse($harness->registrations->stored('reg-2')->canRetry(1));
        static::assertSame(RegistrationState::Submitting, $harness->registrations->stored('reg-3')->status()->state());
        static::assertSame(RegistrationState::AwaitingPairing, $harness->registrations->stored('reg-4')->status()->state());
        static::assertSame(
            ['begin', 'save:reg-1', 'commit', 'publish:CustomerRegistrationFailed', 'begin', 'save:reg-2', 'commit', 'publish:CustomerRegistrationFailed'],
            $harness->journal->entries(),
        );
    }

    public function testHonoursTheLimit(): void
    {
        $harness = self::harness();

        static::assertSame(['reg-1'], array_map(static fn(BatchItem $item): string => $item->subject, self::reconcile($harness, 1)->items()));
    }

    private static function harness(): Harness
    {
        $harness = new Harness();
        // reg-1: last attempt long ago; reg-2: exactly 15 minutes ago (stale); reg-3: 14 min 59 s ago (not stale yet).
        $harness->registration(1, RegistrationState::Submitting);
        foreach ([2 => '2022-03-01T00:00:00Z', 3 => '2022-03-01T00:00:01Z'] as $number => $startedAt) {
            $registration = $harness->registration($number, RegistrationState::Pending);
            $registration->startSubmission(Harness::at($startedAt));
            $registration->releaseEvents();
            $harness->registrations->add($registration);
        }
        $harness->registration(4, RegistrationState::AwaitingPairing);
        $harness->clock->moveTo(self::LATER);
        $harness->journal->clear();

        return $harness;
    }

    private static function reconcile(Harness $harness, ?int $limit): BatchReport
    {
        $handler = new ReconcileStaleRegistrationsHandler($harness->registrations, $harness->clock, $harness->unitOfWork, $harness->policy);

        return $handler(new ReconcileStaleRegistrations($limit));
    }
}
