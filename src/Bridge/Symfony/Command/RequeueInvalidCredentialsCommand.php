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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RequeueAfterInvalidCredentials;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;

#[AsCommand(name: 'urssaf:requeue-invalid-credentials', description: 'Move the records refused for invalid credentials back to pending (after fixing the credentials)')]
final class RequeueInvalidCredentialsCommand extends Command
{
    public function __construct(
        private readonly CustomerRegistrationRepository $registrations,
        private readonly PaymentRequestRepository $paymentRequests,
        private readonly RequeueAfterInvalidCredentialsHandler $requeue,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (required)');
        CommandOptions::addLimit($this);
        CommandOptions::addDryRun($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $tenantId = CommandOptions::requiredTenant($input);
            $limit = CommandOptions::limit($input);
            if (CommandOptions::dryRun($input)) {
                $pageSize = $this->policy->pageSize($limit);

                return CommandReports::renderPlan($output, [
                    ...array_map(
                        static fn(CustomerRegistration $registration): array => [$tenantId->value(), $registration->id()->value()],
                        $this->registrations->findByState($tenantId, RegistrationState::InvalidCredentials, $pageSize),
                    ),
                    ...array_map(
                        static fn(PaymentRequest $paymentRequest): array => [$tenantId->value(), $paymentRequest->id()->value()],
                        $this->paymentRequests->findByState($tenantId, SubmissionState::InvalidCredentials, $pageSize),
                    ),
                ]);
            }

            return $this->locks->exclusively(
                'requeue-invalid-credentials',
                $tenantId->value(),
                function () use ($output, $tenantId, $limit): int {
                    $report = ($this->requeue)(new RequeueAfterInvalidCredentials($tenantId, $limit));
                    $table = new Table($output);
                    $table->setHeaders(['Records', 'Requeued']);
                    $table->setRows([['customer registrations', $report->registrations], ['payment requests', $report->paymentRequests]]);
                    $table->render();

                    return Command::SUCCESS;
                },
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
