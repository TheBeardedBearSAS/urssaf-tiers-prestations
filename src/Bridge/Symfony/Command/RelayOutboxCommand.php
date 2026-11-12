<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;

#[AsCommand(name: 'urssaf:outbox:relay', description: 'Delivers the pending integration events of the outbox')]
final class RelayOutboxCommand extends Command
{
    public function __construct(private readonly RelayOutboxHandler $relay, private readonly OperationLocks $locks)
    {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        CommandOptions::addLimit($this);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($input, $output): int {
            $limit = CommandOptions::limit($input);

            return $this->locks->exclusively(
                'outbox.relay',
                '*',
                function () use ($output, $limit): int {
                    $report = ($this->relay)(new RelayOutbox($limit));
                    $table = new Table($output);
                    $table->setHeaders(['Dispatched', 'Failed', 'Deferred']);
                    $table->setRows([[$report->dispatched, $report->failed, $report->deferred]]);
                    $table->render();

                    return $report->failed > 0 ? Command::FAILURE : Command::SUCCESS;
                },
                static fn(string $resource): int => CommandReports::renderBusy($output, $resource),
            );
        });
    }
}
