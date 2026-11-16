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
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllCustomerPairings;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllCustomerPairingsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairing;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairingHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

#[AsCommand(name: 'urssaf:customers:sync-pairing', description: 'Poll the pairing status of the registered customers (method 020)')]
final class SyncCustomerPairingCommand extends Command
{
    public function __construct(
        private readonly CustomerRegistrationRepository $registrations,
        private readonly SyncCustomerPairingHandler $syncTenant,
        private readonly SyncAllCustomerPairingsHandler $syncAll,
        private readonly WorkflowPolicy $policy,
        private readonly OperationLocks $locks,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: every tenant with registrations whose pairing is followed)');
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
                $rows = [];
                foreach ($tenant instanceof TenantId ? [$tenant] : $this->registrations->tenantsWithPairingToFollow() as $tenantId) {
                    foreach ($this->registrations->findPairingToFollow($tenantId, $this->policy->pageSize($limit)) as $registration) {
                        $rows[] = self::row($registration);
                    }
                }

                return CommandReports::renderPlan($output, $rows);
            }

            return $this->locks->exclusively(
                'customers.sync-pairing',
                $tenant instanceof TenantId ? $tenant->value() : '*',
                fn(): int => CommandReports::renderReport($output, $tenant instanceof TenantId
                    ? ($this->syncTenant)(new SyncCustomerPairing($tenant, $limit))
                    : ($this->syncAll)(new SyncAllCustomerPairings($limit))),
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }

    /**
     * @return array{string, string}
     */
    private static function row(CustomerRegistration $registration): array
    {
        return [$registration->tenantId()->value(), $registration->id()->value()];
    }
}
