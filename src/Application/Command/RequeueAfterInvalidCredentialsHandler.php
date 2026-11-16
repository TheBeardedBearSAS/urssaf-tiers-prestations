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

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\RequeueReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Once the tenant credentials are fixed: moves up to "limit" registrations and up to "limit" payment requests from
 * invalid_credentials back to pending (one transaction), so that they get submitted again. Run it again while the
 * returned counts reach the limit.
 */
final readonly class RequeueAfterInvalidCredentialsHandler
{
    public function __construct(
        private CustomerRegistrationRepository $registrations,
        private PaymentRequestRepository $paymentRequests,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    public function __invoke(RequeueAfterInvalidCredentials $command): RequeueReport
    {
        $limit = $this->policy->pageSize($command->limit);
        $registrations = $this->registrations->findByState($command->tenantId, RegistrationState::InvalidCredentials, $limit);
        $paymentRequests = $this->paymentRequests->findByState($command->tenantId, SubmissionState::InvalidCredentials, $limit);

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($registrations, $paymentRequests): RequeueReport {
            $now = Instant::fromDateTime($this->clock->now());
            $released = [];
            foreach ($registrations as $registration) {
                $registration->retry($now, $this->policy->maxSubmissionAttempts());
                $this->registrations->save($registration);
                $released[] = $registration->releaseEvents();
            }
            foreach ($paymentRequests as $paymentRequest) {
                $paymentRequest->retry($now, $this->policy->maxSubmissionAttempts());
                $this->paymentRequests->save($paymentRequest);
                $released[] = $paymentRequest->releaseEvents();
            }
            $events->record(...array_merge(...$released));

            return new RequeueReport(\count($registrations), \count($paymentRequests));
        });
    }
}
