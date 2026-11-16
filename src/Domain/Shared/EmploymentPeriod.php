<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Employment period of a payment request or of a service line (dateDebutEmploi / dateFinEmploi).
 *
 * Invariants (checked at construction):
 * - end >= start                                   => "employment_period.end_before_start" (ERR_DATE_FIN_AVANT_DATE_DEB)
 * - start and end in the same calendar month, both in the business time zone AND in UTC
 *                                                  => "employment_period.not_single_month" (ERR_PERIODE_EMPLOI_MOIS_NON_UNIQUE)
 *
 * Time-dependent rules (not in the future, after the pairing acceptance) are exposed as queries.
 */
final readonly class EmploymentPeriod
{
    private const SUBJECT = 'employment_period';

    private function __construct(private Instant $start, private Instant $end, private DateTimeZone $businessTimeZone) {}

    /**
     * @throws InvalidValue
     */
    public static function between(Instant $start, Instant $end, DateTimeZone $businessTimeZone): self
    {
        if ($end->isBefore($start)) {
            throw InvalidValue::because(
                self::SUBJECT . '.end_before_start',
                'Invalid employment_period: the end must not be before the start.',
            );
        }
        $utc = new DateTimeZone('UTC');
        if (
            !$start->calendarDate($businessTimeZone)->isSameMonthAs($end->calendarDate($businessTimeZone))
            || !$start->calendarDate($utc)->isSameMonthAs($end->calendarDate($utc))
        ) {
            throw InvalidValue::because(
                self::SUBJECT . '.not_single_month',
                'Invalid employment_period: the start and the end must belong to the same calendar month.',
            );
        }

        return new self($start, $end, $businessTimeZone);
    }

    public function start(): Instant
    {
        return $this->start;
    }

    public function end(): Instant
    {
        return $this->end;
    }

    public function businessTimeZone(): DateTimeZone
    {
        return $this->businessTimeZone;
    }

    /**
     * Start date as seen in the business time zone.
     */
    public function startDate(): CalendarDate
    {
        return $this->start->calendarDate($this->businessTimeZone);
    }

    /**
     * End date as seen in the business time zone.
     */
    public function endDate(): CalendarDate
    {
        return $this->end->calendarDate($this->businessTimeZone);
    }

    /**
     * True when the period starts strictly before the given instant (e.g. before the pairing acceptance:
     * URSSAF then answers ERR_LIEN_PARTICULIER_PRESTATAIRE).
     */
    public function startsBefore(Instant $instant): bool
    {
        return $this->start->isBefore($instant);
    }

    /**
     * True when any part of the period is after "now" (URSSAF answers ERR_DATE_FUTUR).
     */
    public function isInFuture(Instant $now): bool
    {
        return $this->end->isAfter($now);
    }

    /**
     * True when the other period lies entirely within this one (bounds included).
     */
    public function contains(self $other): bool
    {
        return !$other->start->isBefore($this->start) && !$other->end->isAfter($this->end);
    }

    public function equals(self $other): bool
    {
        return $this->start->equals($other->start)
            && $this->end->equals($other->end)
            && $this->businessTimeZone->getName() === $other->businessTimeZone->getName();
    }
}
