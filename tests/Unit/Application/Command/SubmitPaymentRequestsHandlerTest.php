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
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class SubmitPaymentRequestsHandlerTest extends TestCase
{
    public function testSubmitsTheClaimedRequestsInOneCall(): void
    {
        $harness = SubmissionScenario::harness(3, new WorkflowPolicy());
        SubmissionScenario::acceptAll($harness);

        $report = SubmissionScenario::submit($harness, 1, 2, 3);

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::Accepted, ['10']),
            new BatchItem('pr-2', ItemOutcome::Accepted, ['10']),
            new BatchItem('pr-3', ItemOutcome::Accepted, ['10']),
        ], $report->items());
        $stored = $harness->paymentRequests->stored('pr-2');
        static::assertSame(SubmissionState::Submitted, $stored->state());
        static::assertSame('U-INV-2', $stored->paymentRequestId()?->value());
        static::assertSame(SubmissionScenario::LATER, $stored->timeline()->submittedAt()?->toIso8601());
        static::assertSame(SubmissionScenario::LATER, $stored->timeline()->submissionStartedAt()?->toIso8601());
        static::assertSame(['tenant-1'], $harness->gateway->tenants());
        static::assertSame([
            'claim:pr-1', 'claim:pr-2', 'claim:pr-3', 'submit:3',
            'begin', 'save:pr-1', 'save:pr-2', 'save:pr-3', 'commit',
            'publish:PaymentRequestSubmitted', 'publish:PaymentRequestSubmitted', 'publish:PaymentRequestSubmitted',
        ], $harness->journal->entries());
    }


    public function testChunksTheRequestsByTheBatchSize(): void
    {
        $harness = SubmissionScenario::harness(23, new WorkflowPolicy());
        SubmissionScenario::acceptAll($harness);

        $report = SubmissionScenario::submit($harness, ...range(1, 23));

        static::assertSame([10, 10, 3], array_map(\count(...), $harness->gateway->submissions()));
        static::assertSame(['INV-21', 'INV-22', 'INV-23'], $harness->gateway->submissions()[2]);
        static::assertSame(23, $report->count(ItemOutcome::Accepted));
        static::assertSame(3, $harness->transactions->committed());
    }


    public function testUsesTheConfiguredBatchSize(): void
    {
        $harness = SubmissionScenario::harness(9, SubmissionScenario::policy(4));
        SubmissionScenario::acceptAll($harness);

        SubmissionScenario::submit($harness, ...range(1, 9));

        static::assertSame([4, 4, 1], array_map(\count(...), $harness->gateway->submissions()));
    }


    public function testAppliesEachOutcomeOfTheBatch(): void
    {
        $harness = SubmissionScenario::harness(3, new WorkflowPolicy());
        $outcomes = [
            SubmissionOutcome::accepted('INV-1', PaymentRequestId::fromString('U-1'), '10'),
            SubmissionOutcome::rejected('INV-2', 'ERR_X', ApiErrors::of(ApiError::of('ERR_X', 'Refused.', 'd')), RejectionKind::Final),
        ];
        $harness->gateway->willSubmitWith(static fn(): array => $outcomes);

        $report = SubmissionScenario::submit($harness, 1, 2, 3);

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::Accepted, ['10']),
            new BatchItem('pr-2', ItemOutcome::Rejected, ['ERR_X']),
            new BatchItem('pr-3', ItemOutcome::Unconfirmed),
        ], $report->items());
        static::assertSame(SubmissionState::Submitted, SubmissionScenario::state($harness, 1));
        static::assertSame(SubmissionState::Rejected, SubmissionScenario::state($harness, 2));
        static::assertSame('ERR_X', $harness->paymentRequests->stored('pr-2')->submissionErrors()[0]->code());
        static::assertSame('ERR_X', $harness->paymentRequests->stored('pr-2')->rawUrssafStatus());
        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 3));
        static::assertSame(['PaymentRequestSubmitted', 'PaymentRequestRejectedByUrssaf'], $harness->events->names());
    }


    public function testSkipsUnknownForeignAndAlreadyClaimedRequests(): void
    {
        $harness = SubmissionScenario::harness(2, new WorkflowPolicy());
        $harness->paymentRequest(3, SubmissionState::Pending, Harness::OTHER_TENANT);
        $harness->paymentRequest(4, SubmissionState::Submitted);
        $harness->paymentRequests->refuseClaim(LocalPaymentRequestId::fromString('pr-2'));
        SubmissionScenario::acceptAll($harness);

        $report = SubmissionScenario::submit($harness, 9, 3, 4, 2, 1);

        static::assertEquals([
            new BatchItem('pr-9', ItemOutcome::NotFound),
            new BatchItem('pr-3', ItemOutcome::NotFound),
            new BatchItem('pr-4', ItemOutcome::NotClaimed),
            new BatchItem('pr-2', ItemOutcome::NotClaimed),
            new BatchItem('pr-1', ItemOutcome::Accepted, ['10']),
        ], $report->items());
        static::assertSame([['INV-1']], $harness->gateway->submissions());
        static::assertSame(SubmissionState::Pending, SubmissionScenario::state($harness, 3));
    }


    public function testDoesNotCallUrssafWithoutClaimedRequest(): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $harness->paymentRequests->refuseClaim(LocalPaymentRequestId::fromString('pr-1'));

        static::assertSame(1, SubmissionScenario::submit($harness, 1)->count(ItemOutcome::NotClaimed));
        static::assertSame([], $harness->gateway->submissions());
    }


    public function testCommandKeepsAListOfIds(): void
    {
        $id = LocalPaymentRequestId::fromString('pr-1');

        static::assertSame([$id], SubmitPaymentRequests::of(Harness::tenant(), ...['first' => $id])->paymentRequestIds);
    }
}
