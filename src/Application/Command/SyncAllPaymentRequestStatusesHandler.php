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
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Runs {@see SyncPaymentRequestStatusesHandler} for each tenant having pollable payment requests. A tenant whose
 * credentials are refused is reported ({@see ItemOutcome::InvalidCredentials}, subject = tenant id) and the run goes
 * on with the next tenant; temporary failures propagate.
 */
final readonly class SyncAllPaymentRequestStatusesHandler
{
    public function __construct(
        private PaymentRequestRepository $paymentRequests,
        private SyncPaymentRequestStatusesHandler $syncTenant,
        private Clock $clock,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws RetryableException
     */
    public function __invoke(SyncAllPaymentRequestStatuses $command): BatchReport
    {
        $tenants = $this->paymentRequests->tenantsWithPollable(
            Instant::fromDateTime($this->clock->now()),
            $this->policy->pollingWindow(),
        );
        $report = BatchReport::of();
        foreach ($tenants as $tenantId) {
            try {
                $tenantReport = ($this->syncTenant)(new SyncPaymentRequestStatuses($tenantId, $command->limitPerTenant));
            } catch (UrssafAuthenticationFailed) {
                $tenantReport = BatchReport::of(new BatchItem($tenantId->value(), ItemOutcome::InvalidCredentials));
            }
            $report = $report->merge($tenantReport);
        }

        return $report;
    }
}
