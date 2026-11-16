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
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Settles the customer registrations (every tenant) left submitting for at least the policy "staleSubmissionAfter"
 * (oldest attempt first): the process that called method 010 probably crashed, and URSSAF may or may not have
 * registered the customer.
 *
 * URSSAF offers no way to find a registration from the customer data (method 020 needs the URSSAF customer id, which
 * only the lost 010 answer carried), and sending the registration again would be refused as already registered if
 * the first call succeeded. The outcome is therefore never guessed:
 * each registration is moved to error with the cause {@see SubmitCustomerRegistrationHandler::OUTCOME_UNKNOWN}, never
 * retried automatically ({@see ItemOutcome::ManualReview}), one transaction per registration. An operator checks the
 * customer (e.g. in the URSSAF provider space) and, once sure that it was not registered, retries it
 * (CustomerRegistration::retry()).
 */
final readonly class ReconcileStaleRegistrationsHandler
{
    public function __construct(
        private CustomerRegistrationRepository $registrations,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    public function __invoke(ReconcileStaleRegistrations $command): BatchReport
    {
        $olderThan = Instant::fromDateTime($this->clock->now()->sub($this->policy->staleSubmissionAfter()));
        $stale = $this->registrations->findStaleSubmissions($olderThan, $this->policy->pageSize($command->limit));

        return BatchReport::of(...array_map($this->settle(...), $stale));
    }

    private function settle(CustomerRegistration $registration): BatchItem
    {
        return $this->unitOfWork->run(function (RecordedEvents $events) use ($registration): BatchItem {
            $registration->markFailed([RejectionCause::of(
                SubmitCustomerRegistrationHandler::OUTCOME_UNKNOWN,
                'The registration (method 010) was not confirmed: URSSAF may have registered the customer.',
            )], Instant::fromDateTime($this->clock->now()));
            $this->registrations->save($registration);
            $events->record(...$registration->releaseEvents());

            return new BatchItem(
                $registration->id()->value(),
                ItemOutcome::ManualReview,
                [SubmitCustomerRegistrationHandler::OUTCOME_UNKNOWN],
            );
        });
    }
}
