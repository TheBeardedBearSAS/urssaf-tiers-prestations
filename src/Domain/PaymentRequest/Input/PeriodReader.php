<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input;

use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Parses employment periods: bounds at "<path>.start" / "<path>.end", period invariants at "<path>".
 *
 * @internal
 */
final readonly class PeriodReader
{
    public function __construct(private DraftReader $reader, private DateTimeZone $businessTimeZone) {}

    public function required(string $path, ?PeriodDraft $draft): ?EmploymentPeriod
    {
        if (!$draft instanceof PeriodDraft) {
            $this->reader->missing($path);

            return null;
        }

        return $this->read($path, $draft);
    }

    public function optional(string $path, ?PeriodDraft $draft): ?EmploymentPeriod
    {
        return $draft instanceof PeriodDraft ? $this->read($path, $draft) : null;
    }

    private function read(string $path, PeriodDraft $draft): ?EmploymentPeriod
    {
        $start = $this->reader->required($path . '.start', $draft->start, Instant::fromIso8601(...));
        $end = $this->reader->required($path . '.end', $draft->end, Instant::fromIso8601(...));
        if ($start === null || $end === null) {
            return null;
        }
        $timeZone = $this->businessTimeZone;

        return $this->reader->attempt(
            $path,
            static fn(): EmploymentPeriod => EmploymentPeriod::between($start, $end, $timeZone),
        );
    }
}
