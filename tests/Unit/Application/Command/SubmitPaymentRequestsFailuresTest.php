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
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use Throwable;

final class SubmitPaymentRequestsFailuresTest extends TestCase
{
    public function testMarksTheUnsentRequestsInvalidOnRefusedCredentials(): void
    {
        $harness = SubmissionScenario::harness(5, SubmissionScenario::policy(2));
        SubmissionScenario::failOnSecondCall($harness, UrssafAuthenticationFailed::withStatus(403));

        $report = SubmissionScenario::submit($harness, ...range(1, 5));

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::Accepted, ['10']),
            new BatchItem('pr-2', ItemOutcome::Accepted, ['10']),
            new BatchItem('pr-3', ItemOutcome::InvalidCredentials),
            new BatchItem('pr-4', ItemOutcome::InvalidCredentials),
            new BatchItem('pr-5', ItemOutcome::InvalidCredentials),
        ], $report->items());
        static::assertCount(2, $harness->gateway->submissions());
        static::assertSame(SubmissionState::InvalidCredentials, SubmissionScenario::state($harness, 5));
        static::assertSame(SubmissionScenario::LATER, $harness->paymentRequests->stored('pr-5')->timeline()->updatedAt()->toIso8601());
    }


    public function testRequeuesTheUnsentRequestsOnUnavailability(): void
    {
        $harness = SubmissionScenario::harness(5, SubmissionScenario::policy(2));
        $unavailable = UrssafUnavailable::withStatus(Operation::SubmitPaymentRequests, 429, 30);
        SubmissionScenario::failOnSecondCall($harness, $unavailable);

        $failure = SubmissionScenario::retryableFailure($harness);

        static::assertSame($unavailable, $failure->getPrevious());
        static::assertSame(SubmissionState::Submitted, SubmissionScenario::state($harness, 2));
        foreach ([3, 4, 5] as $number) {
            static::assertSame(SubmissionState::Pending, SubmissionScenario::state($harness, $number));
        }
        $requeued = $harness->paymentRequests->stored('pr-3');
        static::assertSame(SubmissionScenario::LATER, $requeued->timeline()->updatedAt()->toIso8601());
        static::assertSame(
            ['begin', 'save:pr-3', 'save:pr-4', 'save:pr-5', 'commit', ...array_fill(0, 3, 'publish:PaymentRequestSubmissionFailed')],
            \array_slice($harness->journal->entries(), -8),
        );
        $errors = $requeued->submissionErrors();
        static::assertCount(1, $errors);
        static::assertSame(SubmitPaymentRequestsHandler::UNAVAILABLE, $errors[0]->code());
        static::assertSame($unavailable->getMessage(), $errors[0]->message());
        static::assertSame(UrssafUnavailable::class, $errors[0]->description());
        static::assertSame(1, $requeued->attempts());
    }


    public function testLeavesABatchThatMayHaveReachedUrssafForReconciliation(): void
    {
        $harness = SubmissionScenario::harness(5, SubmissionScenario::policy(2));
        SubmissionScenario::failOnSecondCall($harness, UrssafUnavailable::dueToTransportError(Operation::SubmitPaymentRequests, new RuntimeException('timeout')));

        static::assertInstanceOf(UrssafUnavailable::class, SubmissionScenario::retryableFailure($harness)->getPrevious());

        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 3));
        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 4));
        static::assertSame(SubmissionState::Pending, SubmissionScenario::state($harness, 5));
    }


    public function testLeavesABatchAnsweredWithAServerErrorForReconciliation(): void
    {
        $harness = SubmissionScenario::harness(5, SubmissionScenario::policy(2));
        $unavailable = UrssafUnavailable::withStatus(Operation::SubmitPaymentRequests, 500, null);
        SubmissionScenario::failOnSecondCall($harness, $unavailable);

        static::assertSame($unavailable, SubmissionScenario::retryableFailure($harness)->getPrevious());

        static::assertSame(SubmissionState::Submitted, SubmissionScenario::state($harness, 2));
        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 3));
        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 4));
        static::assertSame(SubmissionState::Pending, SubmissionScenario::state($harness, 5));
    }

    public function testRejectsTheBatchRefusedAsAWholeAndRequeuesTheFollowingOnes(): void
    {
        $harness = SubmissionScenario::harness(5, SubmissionScenario::policy(2));
        $rejection = UrssafRequestRejected::withErrors(Operation::SubmitPaymentRequests, 400, ApiErrors::of(
            ApiError::of('ERREUR_VALIDATION', 'Invalid.', 'd'),
            ApiError::of('PARAM_INVALIDE', 'Parameter.', 'p'),
        ));
        SubmissionScenario::failOnSecondCall($harness, $rejection);

        $report = SubmissionScenario::submit($harness, ...range(1, 5));

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::Accepted, ['10']),
            new BatchItem('pr-2', ItemOutcome::Accepted, ['10']),
            new BatchItem('pr-3', ItemOutcome::Rejected, ['ERREUR_VALIDATION', 'PARAM_INVALIDE']),
            new BatchItem('pr-4', ItemOutcome::Rejected, ['ERREUR_VALIDATION', 'PARAM_INVALIDE']),
            new BatchItem('pr-5', ItemOutcome::Requeued, [SubmitPaymentRequestsHandler::NOT_SENT]),
        ], $report->items());
        static::assertCount(2, $harness->gateway->submissions());
        $rejected = $harness->paymentRequests->stored('pr-4');
        static::assertSame(SubmissionState::Rejected, $rejected->state());
        static::assertSame('Invalid.', $rejected->submissionErrors()[0]->message());
        static::assertSame('p', $rejected->submissionErrors()[1]->description());
        static::assertNull($rejected->rawUrssafStatus());
        $requeued = $harness->paymentRequests->stored('pr-5');
        static::assertSame(SubmissionState::Pending, $requeued->state());
        static::assertSame('Not sent: URSSAF refused a previous call of the same run.', $requeued->submissionErrors()[0]->message());
        static::assertSame($rejection->getMessage(), $requeued->submissionErrors()[0]->description());
        static::assertSame(
            ['begin', 'save:pr-3', 'save:pr-4', 'commit', 'publish:PaymentRequestRejectedByUrssaf', 'publish:PaymentRequestRejectedByUrssaf',
                'begin', 'save:pr-5', 'commit', 'publish:PaymentRequestSubmissionFailed'],
            \array_slice($harness->journal->entries(), -10),
        );
    }

    public function testRejectsALastBatchRefusedWithoutErrorCode(): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $rejection = UrssafRequestRejected::withErrors(Operation::SubmitPaymentRequests, 400, ApiErrors::none());
        $harness->gateway->willSubmitWith(static fn(): Throwable => $rejection);

        $report = SubmissionScenario::submit($harness, 1);

        static::assertEquals([new BatchItem('pr-1', ItemOutcome::Rejected, [SubmitPaymentRequestsHandler::CALL_REJECTED])], $report->items());
        $error = $harness->paymentRequests->stored('pr-1')->submissionErrors()[0];
        static::assertSame($rejection->getMessage(), $error->message());
        static::assertSame('', $error->description());
        static::assertSame(1, $harness->transactions->committed());
    }

    public function testRequeuesEverythingOnALocalRateLimit(): void
    {
        $harness = SubmissionScenario::harness(3, SubmissionScenario::policy(2));
        $harness->gateway->willSubmitWith(static fn(): Throwable => RateLimitExceeded::forOperation(Operation::SubmitPaymentRequests, 5));

        static::assertInstanceOf(RateLimitExceeded::class, SubmissionScenario::retryableFailure($harness)->getPrevious());

        static::assertCount(1, $harness->gateway->submissions());
        foreach ([1, 2, 3] as $number) {
            static::assertSame(SubmissionState::Pending, SubmissionScenario::state($harness, $number));
        }
    }


    public function testLeavesTheRequestsSubmittingOnAnUnexpectedFailure(): void
    {
        $harness = SubmissionScenario::harness(2, new WorkflowPolicy());
        $failure = new RuntimeException('boom');
        $harness->gateway->willSubmitWith(static fn(): Throwable => $failure);

        try {
            SubmissionScenario::submit($harness, 1, 2);
            static::fail('Expected the failure to propagate.');
        } catch (RuntimeException $exception) {
            static::assertSame($failure, $exception);
        }
        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 1));
        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 2));
    }

    public function testLeavesTheUnsentRequestsInErrorOnceTheMaximumNumberOfAttemptsIsReached(): void
    {
        $harness = SubmissionScenario::harness(2, new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P90D'), new DateInterval('PT15M'), 'menage.fr', 10, 100, 1000, 1));
        $harness->gateway->willSubmitWith(static fn(): Throwable => UrssafUnavailable::withStatus(Operation::SubmitPaymentRequests, 429, 30));

        static::assertInstanceOf(UrssafUnavailable::class, SubmissionScenario::retryableFailure($harness)->getPrevious());

        foreach ([1, 2] as $number) {
            static::assertSame(SubmissionState::Error, SubmissionScenario::state($harness, $number));
        }
        static::assertSame(
            ['begin', 'save:pr-1', 'save:pr-2', 'commit', 'publish:PaymentRequestSubmissionFailed', 'publish:PaymentRequestSubmissionFailed'],
            \array_slice($harness->journal->entries(), -6),
        );
    }
}
