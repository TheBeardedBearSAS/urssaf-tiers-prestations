<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command;

use DateInterval;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\PurgeOutbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\PurgeOutboxHandler;

#[AsCommand(name: 'urssaf:outbox:purge', description: 'Deletes the outbox entries dispatched for longer than the retention')]
final class PurgeOutboxCommand extends Command
{
    public function __construct(private readonly PurgeOutboxHandler $purge, private readonly DateInterval $retention)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return CommandReports::guard($output, function () use ($output): int {
            $output->writeln(\sprintf('%d dispatched outbox entries deleted.', ($this->purge)(new PurgeOutbox($this->retention))));

            return Command::SUCCESS;
        });
    }
}
