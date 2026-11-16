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

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;

/**
 * Submits the oldest pending payment requests of a tenant through {@see SubmitPaymentRequestsHandler}.
 */
final readonly class SubmitPendingPaymentRequestsHandler
{
    public function __construct(
        private PaymentRequestRepository $paymentRequests,
        private SubmitPaymentRequestsHandler $submit,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws RetryableUrssafFailure
     */
    public function __invoke(SubmitPendingPaymentRequests $command): BatchReport
    {
        $pending = $this->paymentRequests->findByState(
            $command->tenantId,
            SubmissionState::Pending,
            $this->policy->pageSize($command->limit),
        );

        return ($this->submit)(SubmitPaymentRequests::of(
            $command->tenantId,
            ...array_map(static fn(PaymentRequest $request): LocalPaymentRequestId => $request->id(), $pending),
        ));
    }
}
