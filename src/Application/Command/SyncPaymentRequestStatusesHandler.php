<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestFinder;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\TransferInfo as UrssafTransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Rejection;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Polls method 070 (by URSSAF ids, 10 per call) for the pollable payment requests of a tenant (within the policy
 * polling window: maximum polling age, settled and unknown status ages), least recently polled first, and applies each snapshot in one transaction per call:
 * - snapshot of a requested id -> status, transfer and rejection applied ({@see ItemOutcome::Changed} when the status
 *   changed, {@see ItemOutcome::Unchanged} otherwise);
 * - requested id missing from the answer -> only the poll instant is recorded, so that the other requests get polled
 *   first next time ({@see ItemOutcome::NotFound});
 * - snapshot of an id that was not requested -> {@see ItemOutcome::Ignored} (subject = URSSAF id);
 * - HTTP 400 for a call -> its requests are left untouched ({@see ItemOutcome::Failed}).
 * Credential failures and temporary failures ({@see RetryableException}) propagate; earlier calls stay committed.
 */
final readonly class SyncPaymentRequestStatusesHandler
{
    public function __construct(
        private PaymentRequestRepository $paymentRequests,
        private PaymentRequestFinder $finder,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws UrssafAuthenticationFailed
     * @throws RetryableException
     */
    public function __invoke(SyncPaymentRequestStatuses $command): BatchReport
    {
        $pollable = $this->paymentRequests->findPollable(
            $command->tenantId,
            $this->now(),
            $this->policy->pollingWindow(),
            $this->policy->pageSize($command->limit),
        );
        $report = BatchReport::of();
        foreach (array_chunk($pollable, PaymentRequestSearch::MAX_ITEMS) as $chunk) {
            $report = $report->merge($this->poll($command->tenantId, $chunk));
        }

        return $report;
    }

    /**
     * @param list<PaymentRequest> $requests
     */
    private function poll(TenantId $tenantId, array $requests): BatchReport
    {
        $byUrssafId = [];
        $urssafIds = [];
        foreach ($requests as $request) {
            // Guaranteed for submitted requests.
            /** @var PaymentRequestId $urssafId */
            $urssafId = $request->paymentRequestId();
            $byUrssafId[$urssafId->value()] = $request;
            $urssafIds[] = $urssafId;
        }

        try {
            $snapshots = $this->finder->search($tenantId, PaymentRequestSearch::byPaymentRequestIds(...$urssafIds));
        } catch (UrssafRequestRejected $rejection) {
            return BatchReport::of(...array_map(
                static fn(PaymentRequest $request): BatchItem => new BatchItem($request->id()->value(), ItemOutcome::Failed, $rejection->errors()->codes()),
                $requests,
            ));
        }

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($byUrssafId, $snapshots): BatchReport {
            $now = $this->now();
            $items = [];
            foreach ($snapshots as $snapshot) {
                $request = $byUrssafId[$snapshot->paymentRequestId()->value()] ?? null;
                $items[] = $request instanceof PaymentRequest
                    ? $this->apply($request, $snapshot, $now, $events)
                    : new BatchItem($snapshot->paymentRequestId()->value(), ItemOutcome::Ignored, [$snapshot->status()->code()]);
                unset($byUrssafId[$snapshot->paymentRequestId()->value()]);
            }
            foreach ($byUrssafId as $missing) {
                $tracking = $missing->tracking();
                $missing->applyUrssafStatus('', $tracking->transfer(), $tracking->rejection(), $now);
                $this->paymentRequests->save($missing);
                $items[] = new BatchItem($missing->id()->value(), ItemOutcome::NotFound);
            }

            return BatchReport::of(...$items);
        });
    }

    private function apply(PaymentRequest $request, PaymentRequestSnapshot $snapshot, Instant $now, RecordedEvents $events): BatchItem
    {
        $transfer = $snapshot->transfer();
        $rejection = $snapshot->rejection();
        $request->applyUrssafStatus(
            $snapshot->status()->code(),
            $transfer instanceof UrssafTransferInfo ? TransferInfo::of($transfer->amount(), $transfer->valueDate()) : null,
            !$rejection instanceof RejectionInfo || trim($rejection->code()) === '' ? null : Rejection::of($rejection->code(), $rejection->comment()),
            $now,
        );
        $this->paymentRequests->save($request);
        $released = $request->releaseEvents();
        $events->record(...$released);

        return new BatchItem(
            $request->id()->value(),
            $released === [] ? ItemOutcome::Unchanged : ItemOutcome::Changed,
            [$snapshot->status()->code()],
        );
    }

    private function now(): Instant
    {
        return Instant::fromDateTime($this->clock->now());
    }
}
