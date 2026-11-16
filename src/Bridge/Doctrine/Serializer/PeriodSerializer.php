<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer;

use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Explicit JSON shape of an {@see EmploymentPeriod} stored inside a service line.
 *
 * @phpstan-type PeriodData array{start: string, end: string, timeZone: string}
 */
final class PeriodSerializer
{
    /**
     * @return PeriodData
     */
    public static function toArray(EmploymentPeriod $period): array
    {
        return [
            'start' => $period->start()->toIso8601(),
            'end' => $period->end()->toIso8601(),
            'timeZone' => $period->businessTimeZone()->getName(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape PeriodData
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): EmploymentPeriod
    {
        /** @var PeriodData $data */
        return EmploymentPeriod::between(
            Instant::fromIso8601($data['start']),
            Instant::fromIso8601($data['end']),
            new DateTimeZone($data['timeZone']),
        );
    }
}
