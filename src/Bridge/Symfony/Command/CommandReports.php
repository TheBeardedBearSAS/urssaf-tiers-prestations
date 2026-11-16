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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\UrssafException;

/**
 * Execution guard and output of the console commands.
 */
final class CommandReports
{
    /** Outcomes that make a command exit with a failure status. */
    private const FAILURES = [ItemOutcome::Failed, ItemOutcome::InvalidCredentials, ItemOutcome::Unconfirmed, ItemOutcome::ManualReview];

    /**
     * Runs a command body, turning package failures and invalid options into an error message and a failure status.
     *
     * @param callable(): int $action
     * @param-immediately-invoked-callable $action
     */
    public static function guard(OutputInterface $output, callable $action): int
    {
        try {
            return $action();
        } catch (UrssafException|InvalidOptionException $failure) {
            $output->writeln(\sprintf('<error>%s</error>', $failure->getMessage()));

            return Command::FAILURE;
        }
    }

    /**
     * Failed item for a record, with a stable code describing the failure.
     */
    public static function failureItem(string $subject, UrssafException $failure): BatchItem
    {
        $code = match (true) {
            $failure instanceof DomainException => $failure->violationCode(),
            $failure instanceof RetryableException => 'urssaf.unavailable',
            default => $failure::class,
        };

        return new BatchItem($subject, ItemOutcome::Failed, [$code]);
    }

    /**
     * Prints the items of a report as a table followed by the count of each outcome.
     */
    public static function renderReport(OutputInterface $output, BatchReport $report): int
    {
        $table = new Table($output);
        $table->setHeaders(['Record', 'Outcome', 'Codes']);
        foreach ($report->items() as $item) {
            $table->addRow([$item->subject, $item->outcome->value, implode(', ', $item->codes)]);
        }
        $table->render();

        $counts = [];
        foreach (ItemOutcome::cases() as $outcome) {
            $count = $report->count($outcome);
            if ($count === 0) {
                continue;
            }
            $counts[] = \sprintf('%s: %d', $outcome->value, $count);
        }
        $output->writeln(\sprintf('%d record(s) processed%s.', $report->total(), $counts === [] ? '' : ' (' . implode(', ', $counts) . ')'));

        foreach (self::FAILURES as $failure) {
            if ($report->count($failure) > 0) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Tells that the same operation is already running (its lock is held): nothing is done, which is not a failure.
     */
    public static function renderBusy(OutputInterface $output, string $resource): int
    {
        $output->writeln(\sprintf('<comment>Already running (lock "%s" is held): skipped.</comment>', $resource));

        return Command::SUCCESS;
    }

    /**
     * Prints what a dry run would process.
     *
     * @param list<array{string, string}> $rows tenant id and record identifier
     */
    public static function renderPlan(OutputInterface $output, array $rows): int
    {
        $table = new Table($output);
        $table->setHeaders(['Tenant', 'Record']);
        $table->setRows($rows);
        $table->render();
        $output->writeln(\sprintf('Dry run: %d record(s) would be processed, URSSAF was not called.', \count($rows)));

        return Command::SUCCESS;
    }
}
