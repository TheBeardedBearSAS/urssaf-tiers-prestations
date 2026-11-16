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
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllPaymentRequestStatuses;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatuses;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

#[AsCommand(name: 'urssaf:payment-requests:sync-statuses', description: 'Poll the status of the submitted payment requests (method 070)')]
final class SyncPaymentStatusesCommand extends Command
{
    public function __construct(
        private readonly PaymentRequestRepository $paymentRequests,
        private readonly SyncPaymentRequestStatusesHandler $syncTenant,
        private readonly SyncAllPaymentRequestStatusesHandler $syncAll,
        private readonly Clock $clock,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: every tenant with payment requests to poll)');
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $limit = CommandOptions::limit($input);
            $tenant = CommandOptions::tenant($input);
            if (CommandOptions::dryRun($input)) {
                $now = Instant::fromDateTime($this->clock->now());
                $rows = [];
                foreach ($tenant instanceof TenantId ? [$tenant] : $this->paymentRequests->tenantsWithPollable($now, $this->policy->pollingWindow()) as $tenantId) {
                    foreach ($this->paymentRequests->findPollable($tenantId, $now, $this->policy->pollingWindow(), $this->policy->pageSize($limit)) as $paymentRequest) {
                        $rows[] = [$tenantId->value(), $paymentRequest->id()->value()];
                    }
                }

                return CommandReports::renderPlan($output, $rows);
            }

            return $this->locks->exclusively(
                'payment-requests.sync-statuses',
                $tenant instanceof TenantId ? $tenant->value() : '*',
                fn(): int => CommandReports::renderReport($output, $tenant instanceof TenantId
                    ? ($this->syncTenant)(new SyncPaymentRequestStatuses($tenant, $limit))
                    : ($this->syncAll)(new SyncAllPaymentRequestStatuses($limit))),
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
