<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestFinder;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\SubmissionOutcomeApplier;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Reconciles the payment requests (every tenant) left submitting for at least the policy "staleSubmissionAfter"
 * (oldest first): they are searched by invoice number with method 070 (10 per call, per tenant), one transaction per call:
 * - found -> submitted with the URSSAF id and status ({@see ItemOutcome::Reconciled});
 * - not found -> error (cause {@see self::NOT_FOUND}) then pending again, to be submitted again
 *   ({@see ItemOutcome::Requeued}), unless the maximum number of attempts is reached: left in error
 *   ({@see ItemOutcome::Exhausted}). A requeued request is submitted again by the next run; should URSSAF then
 *   answer that the invoice number already exists, it is left submitting and reconciled again;
 * - HTTP 400 or credentials refused for a call -> left submitting ({@see ItemOutcome::Failed}), the run goes on.
 * Temporary failures ({@see RetryableException}) propagate; earlier calls stay committed.
 */
final readonly class ReconcileStaleSubmissionsHandler
{
    public const NOT_FOUND = 'urssaf.not_found';
    public const AUTHENTICATION_FAILED = 'urssaf.authentication_failed';

    public function __construct(
        private PaymentRequestRepository $paymentRequests,
        private PaymentRequestFinder $finder,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws RetryableException
     */
    public function __invoke(ReconcileStaleSubmissions $command): BatchReport
    {
        $olderThan = Instant::fromDateTime($this->clock->now()->sub($this->policy->staleSubmissionAfter()));
        $stale = $this->paymentRequests->findStaleSubmissions($olderThan, $this->policy->pageSize($command->limit));
        $byTenant = [];
        foreach ($stale as $request) {
            $byTenant[$request->tenantId()->value()][] = $request;
        }
        $report = BatchReport::of();
        foreach ($byTenant as $requests) {
            foreach (array_chunk($requests, PaymentRequestSearch::MAX_ITEMS) as $chunk) {
                $report = $report->merge($this->reconcile($requests[0]->tenantId(), $chunk));
            }
        }

        return $report;
    }

    /**
     * @param list<PaymentRequest> $requests requests of the tenant
     */
    private function reconcile(TenantId $tenantId, array $requests): BatchReport
    {
        try {
            $snapshots = $this->finder->search($tenantId, PaymentRequestSearch::byInvoiceNumbers(
                ...array_map(static fn(PaymentRequest $request): InvoiceNumber => $request->invoiceNumber(), $requests),
            ));
        } catch (UrssafRequestRejected $rejection) {
            return $this->failed($requests, $rejection->errors()->codes());
        } catch (UrssafAuthenticationFailed) {
            return $this->failed($requests, [self::AUTHENTICATION_FAILED]);
        }
        $found = [];
        foreach ($snapshots as $snapshot) {
            $found[$snapshot->invoiceNumber()] = $snapshot;
        }

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($requests, $found): BatchReport {
            $now = Instant::fromDateTime($this->clock->now());
            $items = [];
            foreach ($requests as $request) {
                $items[] = $this->settle($request, $found[$request->invoiceNumber()->value()] ?? null, $now);
                $this->paymentRequests->save($request);
                $events->record(...$request->releaseEvents());
            }

            return BatchReport::of(...$items);
        });
    }

    private function settle(PaymentRequest $request, ?PaymentRequestSnapshot $snapshot, Instant $now): BatchItem
    {
        if ($snapshot instanceof PaymentRequestSnapshot) {
            $request->reconcileAsSubmitted($snapshot->paymentRequestId(), $snapshot->status()->code(), $now);

            return new BatchItem($request->id()->value(), ItemOutcome::Reconciled, [$snapshot->status()->code()]);
        }
        return SubmissionOutcomeApplier::requeue($request, [SubmissionError::of(
            self::NOT_FOUND,
            'The submission was not confirmed and the invoice number is unknown to URSSAF.',
            '',
        )], $now, $this->policy->maxSubmissionAttempts());
    }

    /**
     * @param list<PaymentRequest> $requests
     * @param list<string>         $codes
     */
    private function failed(array $requests, array $codes): BatchReport
    {
        return BatchReport::of(...array_map(
            static fn(PaymentRequest $request): BatchItem => new BatchItem($request->id()->value(), ItemOutcome::Failed, $codes),
            $requests,
        ));
    }
}
