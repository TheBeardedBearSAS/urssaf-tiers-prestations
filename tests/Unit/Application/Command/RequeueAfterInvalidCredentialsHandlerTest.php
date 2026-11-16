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
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RequeueAfterInvalidCredentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class RequeueAfterInvalidCredentialsHandlerTest extends TestCase
{
    private const LATER = '2022-03-01T00:05:00Z';

    public function testMovesTheRecordsBackToPending(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::InvalidCredentials);
        $harness->registration(2, RegistrationState::Error);
        $harness->registration(3, RegistrationState::InvalidCredentials, Harness::OTHER_TENANT);
        $harness->paymentRequest(1, SubmissionState::InvalidCredentials);
        $harness->paymentRequest(2, SubmissionState::InvalidCredentials);
        $harness->paymentRequest(3, SubmissionState::Error);
        $harness->clock->moveTo(self::LATER);

        $report = self::requeue($harness, null);

        static::assertEquals(new RequeueReport(1, 2), $report);
        static::assertSame(RegistrationState::Pending, $harness->registrations->stored('reg-1')->status()->state());
        static::assertSame(self::LATER, $harness->registrations->stored('reg-1')->status()->updatedAt()->toIso8601());
        static::assertSame(RegistrationState::Error, $harness->registrations->stored('reg-2')->status()->state());
        static::assertSame(RegistrationState::InvalidCredentials, $harness->registrations->stored('reg-3')->status()->state());
        static::assertSame(SubmissionState::Pending, $harness->paymentRequests->stored('pr-2')->state());
        static::assertSame(self::LATER, $harness->paymentRequests->stored('pr-2')->timeline()->updatedAt()->toIso8601());
        static::assertSame(SubmissionState::Error, $harness->paymentRequests->stored('pr-3')->state());
        static::assertSame(
            ['begin', 'save:reg-1', 'save:pr-1', 'save:pr-2', 'commit', 'publish:CustomerRegistrationRetried'],
            $harness->journal->entries(),
        );
    }

    public function testLimitsEachKindOfRecord(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::InvalidCredentials);
        $harness->registration(2, RegistrationState::InvalidCredentials);
        $harness->paymentRequest(1, SubmissionState::InvalidCredentials);
        $harness->paymentRequest(2, SubmissionState::InvalidCredentials);

        static::assertEquals(new RequeueReport(1, 1), self::requeue($harness, 1));
        static::assertEquals(new RequeueReport(1, 1), self::requeue($harness, 1));
        static::assertEquals(new RequeueReport(0, 0), self::requeue($harness, 1));
    }

    private static function requeue(Harness $harness, ?int $limit): RequeueReport
    {
        $handler = new RequeueAfterInvalidCredentialsHandler($harness->registrations, $harness->paymentRequests, $harness->clock, $harness->unitOfWork, $harness->policy);

        return $handler(new RequeueAfterInvalidCredentials(Harness::tenant(), $limit));
    }
}
