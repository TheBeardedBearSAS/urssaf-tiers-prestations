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

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RefusalReason;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;

final class SubmitCustomerRegistrationFailuresTest extends TestCase
{
    public function testRequeuesAndRethrowsARetryableFailureOnUnavailability(): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $unavailable = UrssafUnavailable::withStatus(Operation::RegisterCustomer, 429, 60);
        $harness->gateway->willRegister($unavailable);

        try {
            RegistrationSubmissionScenario::submit($harness);
            static::fail('Expected a retryable failure.');
        } catch (RetryableUrssafFailure $failure) {
            static::assertSame($unavailable, $failure->getPrevious());
        }

        $stored = $harness->registrations->stored('reg-1');
        static::assertSame(RegistrationState::Pending, $stored->status()->state());
        static::assertSame([['urssaf.unavailable', $unavailable->getMessage()]], RegistrationSubmissionScenario::causes($stored));
        static::assertSame(RegistrationSubmissionScenario::LATER, $stored->status()->updatedAt()->toIso8601());
        static::assertSame(
            ['claim:reg-1', 'register:reg-1', 'begin', 'save:reg-1', 'commit', 'publish:CustomerRegistrationFailed', 'publish:CustomerRegistrationRetried'],
            $harness->journal->entries(),
        );
    }


    public function testRequeuesAfterALocalRateLimit(): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $harness->gateway->willRegister(RateLimitExceeded::forOperation(Operation::RegisterCustomer, 10));

        $this->expectException(RetryableUrssafFailure::class);

        try {
            RegistrationSubmissionScenario::submit($harness);
        } finally {
            static::assertSame(RegistrationState::Pending, $harness->registrations->stored('reg-1')->status()->state());
        }
    }


    public function testLeavesTheRegistrationForReviewAndRethrowsAnUnexpectedFailure(): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $failure = new RuntimeException('boom');
        $harness->gateway->willRegister($failure);

        try {
            RegistrationSubmissionScenario::submit($harness);
            static::fail('Expected the failure to propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }

        $stored = $harness->registrations->stored('reg-1');
        static::assertSame(RegistrationState::Error, $stored->status()->state());
        static::assertSame([[SubmitCustomerRegistrationHandler::OUTCOME_UNKNOWN, RuntimeException::class]], RegistrationSubmissionScenario::causes($stored));
        static::assertSame(['CustomerRegistrationFailed'], $harness->events->names());
    }

    /**
     * @return iterable<string, array{UrssafUnavailable}>
     */
    public static function provideFailuresThatMayHaveReachedUrssaf(): iterable
    {
        yield 'timeout' => [UrssafUnavailable::dueToTransportError(Operation::RegisterCustomer, new RuntimeException('timeout'))];
        yield 'server error' => [UrssafUnavailable::withStatus(Operation::RegisterCustomer, 503, null)];
    }

    #[DataProvider('provideFailuresThatMayHaveReachedUrssaf')]
    public function testLeavesTheRegistrationForReviewWhenUrssafMayHaveRegisteredTheCustomer(UrssafUnavailable $failure): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $harness->gateway->willRegister($failure);

        static::assertSame(ItemOutcome::ManualReview, RegistrationSubmissionScenario::submit($harness));

        $stored = $harness->registrations->stored('reg-1');
        static::assertSame(RegistrationState::Error, $stored->status()->state());
        static::assertSame([[SubmitCustomerRegistrationHandler::OUTCOME_UNKNOWN, $failure->getMessage()]], RegistrationSubmissionScenario::causes($stored));
        static::assertSame(RegistrationSubmissionScenario::LATER, $stored->status()->updatedAt()->toIso8601());
        static::assertSame(
            ['claim:reg-1', 'register:reg-1', 'begin', 'save:reg-1', 'commit', 'publish:CustomerRegistrationFailed'],
            $harness->journal->entries(),
        );
    }

    public function testLeavesAnAlreadyRegisteredCustomerForReview(): void
    {
        $harness = RegistrationSubmissionScenario::harness();
        $harness->gateway->willRegister(UrssafRequestRejected::withErrors(
            Operation::RegisterCustomer,
            400,
            ApiErrors::of(ApiError::of('ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE', 'Link exists.', '')),
            RefusalReason::AlreadyRegistered,
        ));

        static::assertSame(ItemOutcome::ManualReview, RegistrationSubmissionScenario::submit($harness));

        $stored = $harness->registrations->stored('reg-1');
        static::assertSame(RegistrationState::Error, $stored->status()->state());
        static::assertSame([['ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE', 'Link exists.']], RegistrationSubmissionScenario::causes($stored));
        static::assertSame(['CustomerRegistrationFailed'], $harness->events->names());
    }

    public function testLeavesTheRegistrationInErrorOnceTheMaximumNumberOfAttemptsIsReached(): void
    {
        $harness = RegistrationSubmissionScenario::harness(new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P90D'), new DateInterval('PT15M'), 'menage.fr', 10, 100, 1000, 1));
        $unavailable = UrssafUnavailable::whileAuthenticating(Operation::RegisterCustomer, 503, 60, null);
        $harness->gateway->willRegister($unavailable);

        $this->expectException(RetryableUrssafFailure::class);
        try {
            RegistrationSubmissionScenario::submit($harness);
        } finally {
            $stored = $harness->registrations->stored('reg-1');
            static::assertSame(RegistrationState::Error, $stored->status()->state());
            static::assertSame(1, $stored->status()->attempts());
            static::assertSame([['urssaf.unavailable', $unavailable->getMessage()]], RegistrationSubmissionScenario::causes($stored));
            static::assertSame(
                ['claim:reg-1', 'register:reg-1', 'begin', 'save:reg-1', 'commit', 'publish:CustomerRegistrationFailed'],
                $harness->journal->entries(),
            );
        }
    }
}
