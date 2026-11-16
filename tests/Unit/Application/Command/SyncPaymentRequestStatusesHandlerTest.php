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
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatuses;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Rejection;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\TransferInfo as DomainTransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;
use Throwable;

final class SyncPaymentRequestStatusesHandlerTest extends TestCase
{
    public function testAppliesTheSnapshots(): void
    {
        $harness = StatusScenario::harness(3);
        $previous = $harness->paymentRequests->stored('pr-3');
        $previous->applyUrssafStatus('10', DomainTransferInfo::of(Money::fromCents(5), CalendarDate::fromString('2022-03-01')), Rejection::of('REGUL_AUTRE', 'c'), Harness::at(Harness::NOW));
        $harness->paymentRequests->add($previous);
        $snapshots = [
            StatusScenario::snapshot(2, '10', null, null),
            StatusScenario::snapshot(99, '10', null, null),
            StatusScenario::snapshot(1, '70', TransferInfo::of(Money::fromCents(1102), CalendarDate::fromString('2022-03-05')), RejectionInfo::of('REGUL_MNT_FACT', 'Wrong amount')),
        ];
        $harness->gateway->willFindWith(static fn(): array => $snapshots);

        $report = StatusScenario::sync($harness, null);

        static::assertEquals([
            new BatchItem('pr-2', ItemOutcome::Unchanged, ['10']),
            new BatchItem('urssaf-99', ItemOutcome::Ignored, ['10']),
            new BatchItem('pr-1', ItemOutcome::Changed, ['70']),
            new BatchItem('pr-3', ItemOutcome::NotFound),
        ], $report->items());
        static::assertSame([['urssaf-1', 'urssaf-2', 'urssaf-3']], StatusScenario::searchedIds($harness));
        $paid = $harness->paymentRequests->stored('pr-1');
        static::assertSame('70', $paid->rawUrssafStatus());
        $transfer = $paid->tracking()->transfer();
        $rejection = $paid->tracking()->rejection();
        static::assertNotNull($transfer);
        static::assertNotNull($rejection);
        static::assertSame(1102, $transfer->amount()->cents());
        static::assertSame('2022-03-05', $transfer->valueDate()->toString());
        static::assertSame('REGUL_MNT_FACT', $rejection->code());
        static::assertSame('Wrong amount', $rejection->comment());
        static::assertSame(StatusScenario::LATER, $paid->lastPolledAt()?->toIso8601());
        static::assertSame(StatusScenario::LATER, $harness->paymentRequests->stored('pr-2')->lastPolledAt()?->toIso8601());
        $missing = $harness->paymentRequests->stored('pr-3');
        static::assertSame(StatusScenario::LATER, $missing->lastPolledAt()?->toIso8601());
        static::assertSame(5, $missing->tracking()->transfer()?->amount()->cents());
        static::assertSame('REGUL_AUTRE', $missing->tracking()->rejection()?->code());
        static::assertSame('10', $missing->rawUrssafStatus());
        static::assertSame(['PaymentRequestStatusChanged'], $harness->events->names());
        static::assertSame(['search', 'begin', 'save:pr-2', 'save:pr-1', 'save:pr-3', 'commit', 'publish:PaymentRequestStatusChanged'], $harness->journal->entries());
    }


    public function testIgnoresABlankRejectionCode(): void
    {
        $harness = StatusScenario::harness(1);
        $snapshots = [StatusScenario::snapshot(1, '40', null, RejectionInfo::of(' ', 'c'))];
        $harness->gateway->willFindWith(static fn(): array => $snapshots);

        StatusScenario::sync($harness, null);

        static::assertNull($harness->paymentRequests->stored('pr-1')->tracking()->rejection());
        static::assertSame('40', $harness->paymentRequests->stored('pr-1')->rawUrssafStatus());
    }


    public function testChunksTheSearchesByTen(): void
    {
        $harness = StatusScenario::harness(23);
        $harness->gateway->willFindWith(static fn(): array => []);

        $report = StatusScenario::sync($harness, null);

        static::assertSame([10, 10, 3], array_map(\count(...), StatusScenario::searchedIds($harness)));
        static::assertSame(23, $report->count(ItemOutcome::NotFound));
        static::assertSame(3, $harness->transactions->committed());
        static::assertCount(5, StatusScenario::sync($harness, 5)->items());
    }


    public function testReportsARefusedSearchAndGoesOn(): void
    {
        $harness = StatusScenario::harness(11);
        $calls = 0;
        $harness->gateway->willFindWith(static function () use (&$calls): array|Throwable {
            ++$calls;

            return $calls === 1
                ? UrssafRequestRejected::withErrors(Operation::SearchPaymentRequests, 400, ApiErrors::of(ApiError::of('PARAM_INVALIDE', 'm', 'd')))
                : [StatusScenario::snapshot(11, '20', null, null)];
        });

        $report = StatusScenario::sync($harness, null);

        static::assertSame(10, $report->count(ItemOutcome::Failed));
        static::assertEquals(new BatchItem('pr-1', ItemOutcome::Failed, ['PARAM_INVALIDE']), $report->items()[0]);
        static::assertEquals(new BatchItem('pr-11', ItemOutcome::Changed, ['20']), $report->items()[10]);
        static::assertNull($harness->paymentRequests->stored('pr-1')->lastPolledAt());
    }


    public function testPropagatesCredentialAndTemporaryFailures(): void
    {
        foreach ([UrssafAuthenticationFailed::withStatus(401), UrssafUnavailable::withStatus(Operation::SearchPaymentRequests, 500, null)] as $failure) {
            $harness = StatusScenario::harness(1);
            $harness->gateway->willFindWith(static fn(): Throwable => $failure);

            try {
                StatusScenario::sync($harness, null);
                static::fail('Expected the failure to propagate.');
            } catch (Throwable $exception) {
                static::assertSame($failure, $exception);
            }
        }
    }


    public function testUsesTheSettledMaximumAgeOfThePolicy(): void
    {
        $harness = StatusScenario::harness(1);
        $paid = $harness->paymentRequests->stored('pr-1');
        $paid->applyUrssafStatus('70', null, null, Harness::at(Harness::NOW));
        $harness->paymentRequests->add($paid);
        $harness->clock->moveTo('2022-07-01T00:00:00Z');
        $harness->gateway->willFindWith(static fn(): array => []);

        static::assertSame(0, StatusScenario::sync($harness, null)->total());

        $policy = new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P200D'));
        $handler = new SyncPaymentRequestStatusesHandler($harness->paymentRequests, $harness->gateway, $harness->clock, $harness->unitOfWork, $policy);
        static::assertSame(1, $handler(new SyncPaymentRequestStatuses(Harness::tenant(), null))->total());
    }
}
