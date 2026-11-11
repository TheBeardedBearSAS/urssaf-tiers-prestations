<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\UrssafException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

#[AsCommand(name: 'urssaf:customers:submit-pending', description: 'Submit the pending customer registrations to URSSAF (method 010)')]
final class SubmitPendingCustomersCommand extends Command
{
    /**
     * @param list<string> $configuredTenants tenants processed when --tenant is not given
     */
    public function __construct(
        private readonly CustomerRegistrationRepository $registrations,
        private readonly SubmitCustomerRegistrationHandler $submit,
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
            $limit = $this->policy->pageSize(CommandOptions::limit($input));
            $tenants = CommandOptions::tenantsOrConfigured($input, $this->configuredTenants);
            if (CommandOptions::dryRun($input)) {
                $pending = [];
                foreach ($tenants as $tenantId) {
                    array_push($pending, ...$this->registrations->findByState($tenantId, RegistrationState::Pending, $limit));
                }

                return CommandReports::renderPlan($output, array_map(
                    static fn(CustomerRegistration $registration): array => [$registration->tenantId()->value(), $registration->id()->value()],
                    $pending,
                ));
            }
            $report = BatchReport::of();
            foreach ($tenants as $tenantId) {
                $report = $report->merge($this->locks->exclusively(
                    'customers.submit',
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

    /**
     * @param positive-int $limit
     */
    private function submitTenant(TenantId $tenantId, int $limit): BatchReport
    {
        return BatchReport::of(...array_map(
            $this->submitOne(...),
            $this->registrations->findByState($tenantId, RegistrationState::Pending, $limit),
        ));
    }

    private function submitOne(CustomerRegistration $registration): BatchItem
    {
        $subject = $registration->id()->value();
        try {
            return new BatchItem($subject, ($this->submit)(new SubmitCustomerRegistration($registration->id())));
        } catch (UrssafException $failure) {
            return CommandReports::failureItem($subject, $failure);
        }
    }
}
