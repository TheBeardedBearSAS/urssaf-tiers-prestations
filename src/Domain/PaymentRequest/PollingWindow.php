<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use DateInterval;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * How long the URSSAF status of a submitted payment request keeps being polled (method 070):
 * - settledMaxAge: after a settled status (paid, recovered) was observed;
 * - unknownStatusMaxAge: after a status code unknown to this package version was observed (it may never change);
 * - maxPollingAge: after the submission, whatever the status (a request never stays followed forever).
 * Every limit is exclusive: an instant + age strictly after now is still within the window.
 */
final readonly class PollingWindow
{
    public function __construct(
        private DateInterval $settledMaxAge = new DateInterval('P90D'),
        private DateInterval $unknownStatusMaxAge = new DateInterval('P30D'),
        private DateInterval $maxPollingAge = new DateInterval('P1Y'),
    ) {}

    public function settledMaxAge(): DateInterval
    {
        return $this->settledMaxAge;
    }

    public function unknownStatusMaxAge(): DateInterval
    {
        return $this->unknownStatusMaxAge;
    }

    public function maxPollingAge(): DateInterval
    {
        return $this->maxPollingAge;
    }

    /**
     * True when $since + $age is strictly after $now.
     */
    public static function isWithin(Instant $since, DateInterval $age, Instant $now): bool
    {
        return Instant::fromDateTime($since->toDateTimeImmutable()->add($age))->isAfter($now);
    }
}
