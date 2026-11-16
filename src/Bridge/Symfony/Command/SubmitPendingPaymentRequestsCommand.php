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
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPendingPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPendingPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

#[AsCommand(name: 'urssaf:payment-requests:submit-pending', description: 'Submit the pending payment requests to URSSAF (method 050)')]
final class SubmitPendingPaymentRequestsCommand extends Command
{
    /**
     * @param list<string> $configuredTenants tenants processed when --tenant is not given
     */
    public function __construct(
        private readonly PaymentRequestRepository $paymentRequests,
        private readonly SubmitPendingPaymentRequestsHandler $submit,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
        private readonly array $configuredTenants,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: the configured tenants)');
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $limit = CommandOptions::limit($input);
            $tenants = CommandOptions::tenantsOrConfigured($input, $this->configuredTenants);
            if (CommandOptions::dryRun($input)) {
                $rows = [];
                foreach ($tenants as $tenantId) {
                    foreach ($this->paymentRequests->findByState($tenantId, SubmissionState::Pending, $this->policy->pageSize($limit)) as $paymentRequest) {
                        $rows[] = [$tenantId->value(), $paymentRequest->id()->value()];
                    }
                }

                return CommandReports::renderPlan($output, $rows);
            }
            $report = BatchReport::of();
            foreach ($tenants as $tenantId) {
                $report = $report->merge($this->locks->exclusively(
                    'payment-requests.submit',
                    $tenantId->value(),
                    fn(): BatchReport => $this->submitTenant($tenantId, $limit),
                    static function (string $resource) use ($output): BatchReport {
                        CommandReports::renderBusy($output, $resource);

                        return BatchReport::of();
                    },
                ));
            }

            return CommandReports::renderReport($output, $report);
        });
    }

    private function submitTenant(TenantId $tenantId, ?int $limit): BatchReport
    {
        try {
            return ($this->submit)(new SubmitPendingPaymentRequests($tenantId, $limit));
        } catch (UrssafException $failure) {
            return BatchReport::of(CommandReports::failureItem($tenantId->value(), $failure));
        }
    }
}
