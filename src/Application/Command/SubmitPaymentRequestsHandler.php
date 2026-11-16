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
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSubmitter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\SubmissionSettlement;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Claims each payment request (pending -> submitting, a single worker wins), then sends the claimed ones with method
 * 050 by batches of the policy size, each batch outcome being applied in one transaction:
 * - unknown id or request of another tenant -> {@see ItemOutcome::NotFound}; not pending -> {@see ItemOutcome::NotClaimed};
 * - the outcomes are matched to the requests by invoice number (by position when URSSAF does not echo it); a request
 *   without a matching outcome is left submitting and reconciled later ({@see ItemOutcome::Unconfirmed});
 * - each outcome is applied by {@see SubmissionOutcomeApplier} (accepted, rejected, duplicate left submitting,
 *   technical error requeued); a refusal because the customer account is blocked, the mandate expired or the pairing
 *   is missing also moves the customer registration (blocked, mandate_expired, awaiting_pairing), so that its pairing
 *   is polled again;
 * - whole call refused (HTTP 400) -> this batch is rejected with the returned errors, the following batches are moved
 *   back to pending (cause {@see self::NOT_SENT}) and the report is returned;
 * - credentials refused -> this batch and the following ones -> invalid_credentials (report returned, nothing thrown);
 * - temporary failure ({@see RetryableException}) -> the unsent requests, and the current batch unless it may have
 *   reached URSSAF ({@see UrssafUnavailable::mayHaveReachedUrssaf()}: transport error, HTTP 5xx), -> error then pending
 *   again, then {@see RetryableUrssafFailure} is thrown; a batch that may have reached URSSAF stays submitting and is
 *   reconciled by ReconcileStaleSubmissions (never resent blindly: URSSAF would answer ERR_FACTURE_DOUBLON);
 * - any other failure propagates and leaves the claimed requests submitting, to be reconciled.
 * A requeued request is left in error instead once the policy maximum number of attempts is reached
 * ({@see ItemOutcome::Exhausted}).
 */
final readonly class SubmitPaymentRequestsHandler
{
    /**
     * Code of the submission error recorded when URSSAF was temporarily unavailable.
     */
    public const UNAVAILABLE = 'urssaf.unavailable';

    /**
     * Code of the submission error recorded for the batches not sent after a refused call.
     */
    public const NOT_SENT = 'urssaf.not_sent';

    /**
     * Code of the submission error recorded when URSSAF refused a call without any error code.
     */
    public const CALL_REJECTED = 'urssaf.call_rejected';

    private SubmissionSettlement $settlement;

    public function __construct(
        private PaymentRequestRepository $paymentRequests,
        private PaymentRequestSubmitter $submitter,
        private Clock $clock,
        UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
        CustomerRegistrationRepository $registrations,
    ) {
        $this->settlement = new SubmissionSettlement($paymentRequests, $registrations, $unitOfWork, $clock, $policy->maxSubmissionAttempts());
    }

    /**
     * @throws RetryableUrssafFailure
     */
    public function __invoke(SubmitPaymentRequests $command): BatchReport
    {
        $items = [];
        $claimed = [];
        foreach ($command->paymentRequestIds as $id) {
            $claim = $this->claim($command->tenantId, $id);
            if ($claim instanceof BatchItem) {
                $items[] = $claim;

                continue;
            }
            $claimed[] = $claim;
        }
        $batches = array_chunk($claimed, $this->policy->submissionBatchSize());
        foreach ($batches as $index => $batch) {
            try {
                $outcomes = $this->submitter->submit($command->tenantId, ...$batch);
            } catch (UrssafAuthenticationFailed) {
                return BatchReport::of(...$items, ...$this->settlement->invalidCredentials(array_merge(...\array_slice($batches, $index))));
            } catch (UrssafRequestRejected $rejection) {
                return BatchReport::of(...$items, ...$this->refused($batch, array_merge([], ...\array_slice($batches, $index + 1)), $rejection));
            } catch (RetryableException $failure) {
                $mayHaveReachedUrssaf = $failure instanceof UrssafUnavailable && $failure->mayHaveReachedUrssaf();
                $unsent = array_merge([], ...\array_slice($batches, $mayHaveReachedUrssaf ? $index + 1 : $index));
                $this->settlement->requeued($unsent, SubmissionError::of(self::UNAVAILABLE, $failure->getMessage(), $failure::class));

                throw RetryableUrssafFailure::causedBy($failure);
            }
            $items = [...$items, ...$this->settlement->outcomes($batch, $outcomes)];
        }

        return BatchReport::of(...$items);
    }

    /**
     * The refused batch is rejected, the following ones are requeued.
     *
     * @param list<PaymentRequest> $batch
     * @param list<PaymentRequest> $unsent
     *
     * @return list<BatchItem>
     */
    private function refused(array $batch, array $unsent, UrssafRequestRejected $rejection): array
    {
        return [
            ...$this->settlement->rejected($batch, $rejection->errors(), SubmissionError::of(self::CALL_REJECTED, $rejection->getMessage(), '')),
            ...$this->settlement->requeued($unsent, SubmissionError::of(
                self::NOT_SENT,
                'Not sent: URSSAF refused a previous call of the same run.',
                $rejection->getMessage(),
            )),
        ];
    }

    private function claim(TenantId $tenantId, LocalPaymentRequestId $id): PaymentRequest|BatchItem
    {
        try {
            $request = $this->paymentRequests->get($id);
        } catch (RecordNotFound) {
            return new BatchItem($id->value(), ItemOutcome::NotFound);
        }
        if (!$request->tenantId()->equals($tenantId)) {
            return new BatchItem($id->value(), ItemOutcome::NotFound);
        }
        if (!$this->paymentRequests->claimForSubmission($id, $this->now())) {
            return new BatchItem($id->value(), ItemOutcome::NotClaimed);
        }

        return $this->paymentRequests->get($id);
    }

    private function now(): Instant
    {
        return Instant::fromDateTime($this->clock->now());
    }
}
