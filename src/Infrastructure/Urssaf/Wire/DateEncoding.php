<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Date formats of the URSSAF wire contract.
 *
 * Encoding is strict: calendar dates become `YYYY-MM-DDT00:00:00Z`, instants `YYYY-MM-DDTHH:MM:SSZ` (UTC).
 * Decoding is tolerant: `Z`, `.000Z`, numeric offsets and plain `Y-m-d` are accepted.
 */
final class DateEncoding
{
    private const DATE_PREFIX = '/^(\d{4}-\d{2}-\d{2})(?:T.*)?$/D';
    private const PLAIN_DATE = '/^\d{4}-\d{2}-\d{2}$/D';

    public static function encodeCalendarDate(CalendarDate $date): string
    {
        return $date->toString() . 'T00:00:00Z';
    }

    public static function encodeInstant(Instant $instant): string
    {
        return $instant->toIso8601();
    }

    /**
     * Reads the date as written (the date part of the string), without any time zone conversion.
     *
     * @throws UnexpectedUrssafResponse
     */
    public static function decodeCalendarDate(string $value): CalendarDate
    {
        $matches = [];
        if (preg_match(self::DATE_PREFIX, $value, $matches) !== 1) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('"%s" is not a date', $value));
        }

        try {
            return CalendarDate::fromString($matches[1]);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy(\sprintf('"%s" is not a valid calendar date', $value), $exception);
        }
    }

    /**
     * A plain `Y-m-d` is read as midnight UTC.
     *
     * @throws UnexpectedUrssafResponse
     */
    public static function decodeInstant(string $value): Instant
    {
        try {
            return Instant::fromIso8601(preg_match(self::PLAIN_DATE, $value) === 1 ? $value . 'T00:00:00Z' : $value);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy(\sprintf('"%s" is not a valid instant', $value), $exception);
        }
    }
}
