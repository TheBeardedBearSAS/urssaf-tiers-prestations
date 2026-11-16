<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthPlace;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\Civility;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\CivilStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\PersonName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;

/**
 * Parses the civil status fields of a raw customer: civility, birthName, usageName, firstNames, birthDate, birthPlace.*.
 *
 * @internal used by {@see CustomerFactory}
 */
final readonly class CivilStatusParser
{
    /**
     * Returns the civil status, or null after reporting the violations to the collector.
     */
    public function parse(CustomerDraft $draft, ViolationCollector $collector): ?CivilStatus
    {
        $civility = $collector->required('civility', 'civility', $draft->civility, Civility::fromCode(...));
        $birthName = $collector->required('birthName', 'birth_name', $draft->birthName, PersonName::birthName(...));
        $usageName = $collector->optional('usageName', $draft->usageName, PersonName::usageName(...));
        $firstNames = $collector->required('firstNames', 'first_names', $draft->firstNames, PersonName::firstNames(...));
        $birthDate = $collector->required('birthDate', 'birth_date', $draft->birthDate, CalendarDate::fromString(...));
        $birthPlaceCollector = new ViolationCollector();
        $birthPlace = (new BirthPlaceValidator())->parse($draft->birthPlace ?? new BirthPlaceDraft(null, null, null, null), $birthPlaceCollector);
        $collector->merge($birthPlaceCollector->result(), 'birthPlace');
        $invalidUsageName = $usageName === null && $draft->usageName !== null && $draft->usageName !== '';
        if ($civility === null || $birthName === null || $invalidUsageName || $firstNames === null || $birthDate === null || !$birthPlace instanceof BirthPlace) {
            return null;
        }

        return new CivilStatus($civility, $birthName, $usageName, $firstNames, $birthDate, $birthPlace);
    }
}
