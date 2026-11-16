<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Scheduler;

use Override;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * The crons of the package as a Symfony Scheduler schedule: each task runs the console commands through Messenger
 * ({@see RunCommandMessage}), exactly like the crontab of the documentation (same locks, same safety nets).
 */
final readonly class UrssafScheduleProvider implements ScheduleProviderInterface
{
    /** Console commands of each task of the "scheduler.frequencies" option. */
    public const TASKS = [
        'submit_pending' => ['urssaf:customers:submit-pending', 'urssaf:payment-requests:submit-pending'],
        'sync_pairing' => ['urssaf:customers:sync-pairing'],
        'sync_statuses' => ['urssaf:payment-requests:sync-statuses'],
        'reconcile_stale' => ['urssaf:customers:reconcile-stale', 'urssaf:payment-requests:reconcile-stale'],
        'relay_outbox' => ['urssaf:outbox:relay'],
        'purge_outbox' => ['urssaf:outbox:purge'],
    ];

    /**
     * @param array<string, ?string> $frequencies ISO 8601 duration of each task, null to leave it out
     */
    public function __construct(private array $frequencies) {}

    #[Override]
    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        foreach (self::TASKS as $task => $commands) {
            $frequency = $this->frequencies[$task] ?? null;
            if ($frequency === null) {
                continue;
            }
            foreach ($commands as $command) {
                $schedule->add(RecurringMessage::every($frequency, new RunCommandMessage($command)));
            }
        }

        return $schedule;
    }
}
