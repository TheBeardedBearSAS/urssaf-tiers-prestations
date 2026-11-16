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
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleSubmissions;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleSubmissionsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

#[AsCommand(name: 'urssaf:payment-requests:reconcile-stale', description: 'Reconcile the submissions left without answer from URSSAF (searches method 070, never resubmits)')]
final class ReconcileStaleSubmissionsCommand extends Command
{
    public function __construct(
        private readonly PaymentRequestRepository $paymentRequests,
        private readonly ReconcileStaleSubmissionsHandler $reconcile,
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
                    static fn(PaymentRequest $paymentRequest): array => [$paymentRequest->tenantId()->value(), $paymentRequest->id()->value()],
                    $this->paymentRequests->findStaleSubmissions($olderThan, $this->policy->pageSize($limit)),
                ));
            }

            return $this->locks->exclusively(
                'payment-requests.reconcile-stale',
                '*',
                fn(): int => CommandReports::renderReport($output, ($this->reconcile)(new ReconcileStaleSubmissions($limit))),
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
