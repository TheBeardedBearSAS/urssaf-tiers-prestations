<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\TenantOverview;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverview;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Prints the records by tenant and state (table, or JSON for monitoring). Reads the local database only.
 */
#[AsCommand(name: 'urssaf:status', description: 'Counts the customer registrations and payment requests by tenant and state')]
final class WorkflowStatusCommand extends Command
{
    private const OPTION_FORMAT = 'format';
    private const FORMAT_TABLE = 'table';
    private const FORMAT_JSON = 'json';

    public function __construct(private readonly GetWorkflowOverviewHandler $overview)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addTenant($this, 'Tenant id (default: every tenant with records)');
        $this->addOption(self::OPTION_FORMAT, null, InputOption::VALUE_REQUIRED, 'Output format: table or json', self::FORMAT_TABLE);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            // @mago-expect analysis:mixed-assignment Symfony options are untyped: the value is checked on the next line.
            $format = $input->getOption(self::OPTION_FORMAT);
            if ($format !== self::FORMAT_TABLE && $format !== self::FORMAT_JSON) {
                throw new InvalidOptionException('The --format option must be "table" or "json".');
            }
            $tenant = CommandOptions::tenant($input);
            $overviews = ($this->overview)($tenant instanceof TenantId ? GetWorkflowOverview::forTenant($tenant) : GetWorkflowOverview::allTenants());
            if ($format === self::FORMAT_JSON) {
                $output->writeln(json_encode(array_map(static fn(TenantOverview $overview): array => $overview->toArray(), $overviews), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

                return Command::SUCCESS;
            }
            self::render($output, 'Customer registrations', RegistrationState::cases(), array_map(
                static fn(TenantOverview $overview): array => [$overview->tenantId, ...$overview->registrations],
                $overviews,
            ));
            self::render($output, 'Payment requests', SubmissionState::cases(), array_map(
                static fn(TenantOverview $overview): array => [$overview->tenantId, ...$overview->paymentRequests],
                $overviews,
            ));

            return Command::SUCCESS;
        });
    }

    /**
     * @param list<RegistrationState>|list<SubmissionState> $states columns, in the order of the counts of the rows
     * @param list<array<int|string>>                       $rows   tenant id then one count per state
     */
    private static function render(OutputInterface $output, string $title, array $states, array $rows): void
    {
        $output->writeln($title);
        $table = new Table($output);
        $table->setHeaders(['Tenant', ...array_map(static fn(RegistrationState|SubmissionState $state): string => $state->value, $states)]);
        $table->setRows($rows);
        $table->render();
    }
}
