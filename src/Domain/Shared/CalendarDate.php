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

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Date without time nor time zone (birth date, invoice date, deposit date...), "Y-m-d", years 0001 to 9999.
 */
final readonly class CalendarDate implements Stringable
{
    private const SUBJECT = 'calendar_date';
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}$/D';
    private const MONTH_OFFSET = 5;
    private const MONTH_LENGTH = 2;
    private const YEAR_MONTH_LENGTH = 7;

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "calendar_date.invalid_format" or "calendar_date.invalid_date" (e.g. 2023-02-29)
     */
    public static function fromString(string $date): self
    {
        if (preg_match(self::PATTERN, $date) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'Y-m-d');
        }
        [$year, $month, $day] = array_map(intval(...), explode('-', $date));
        if (!checkdate($month, $day, $year)) {
            throw InvalidValue::because(self::SUBJECT . '.invalid_date', 'Invalid calendar_date: the date does not exist.');
        }

        return new self($date);
    }

    /**
     * Calendar date of the given moment as seen in the given time zone.
     */
    public static function fromDateTime(DateTimeInterface $dateTime, DateTimeZone $timeZone): self
    {
        return self::fromString(DateTimeImmutable::createFromInterface($dateTime)->setTimezone($timeZone)->format('Y-m-d'));
    }

    public function year(): int
    {
        return (int) $this->value; // the leading "YYYY" is the numeric prefix
    }

    public function month(): int
    {
        return (int) substr($this->value, self::MONTH_OFFSET, self::MONTH_LENGTH);
    }

    public function isBefore(self $other): bool
    {
        return strcmp($this->value, $other->value) < 0;
    }

    public function isAfter(self $other): bool
    {
        return strcmp($this->value, $other->value) > 0;
    }

    public function isSameMonthAs(self $other): bool
    {
        return strncmp($this->value, $other->value, self::YEAR_MONTH_LENGTH) === 0;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * "Y-m-d".
     */
    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
