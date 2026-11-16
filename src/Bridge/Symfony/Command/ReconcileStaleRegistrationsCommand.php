<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleRegistrations;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleRegistrationsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

#[AsCommand(name: 'urssaf:customers:reconcile-stale', description: 'Settle the registrations left without answer from URSSAF (moved to error for a manual review, never resent automatically)')]
final class ReconcileStaleRegistrationsCommand extends Command
{
    public function __construct(
        private readonly CustomerRegistrationRepository $registrations,
        private readonly ReconcileStaleRegistrationsHandler $reconcile,
        private readonly Clock $clock,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $limit = CommandOptions::limit($input);
            if (CommandOptions::dryRun($input)) {
                $olderThan = Instant::fromDateTime($this->clock->now()->sub($this->policy->staleSubmissionAfter()));

                return CommandReports::renderPlan($output, array_map(
                    static fn(CustomerRegistration $registration): array => [$registration->tenantId()->value(), $registration->id()->value()],
                    $this->registrations->findStaleSubmissions($olderThan, $this->policy->pageSize($limit)),
                ));
            }

            return $this->locks->exclusively(
                'customers.reconcile-stale',
                '*',
                fn(): int => CommandReports::renderReport($output, ($this->reconcile)(new ReconcileStaleRegistrations($limit))),
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
