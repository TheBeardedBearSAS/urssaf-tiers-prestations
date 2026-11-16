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

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Nature of a service (codeNature, api-reference §3.1). Backed by the official code.
 *
 * The 27 cases mirror the official URSSAF table one to one, hence the expected "too many cases" lint finding.
 *
 * @mago-expect lint:too-many-enum-cases
 */
enum NatureCode: string
{
    case HomeChildcareUnderThree = '10';
    case ChildAccompanimentUnderThree = '20';
    case DailyLivingAssistance = '30';
    case PersonalVehicleDriving = '40';
    case OutingAccompaniment = '50';
    case Housework = '60';
    case Gardening = '70';
    case HandymanWork = '80';
    case HomeChildcareOverSix = '90';
    case Tutoring = '100';
    case BeautyCareForDependents = '110';
    case MealPreparation = '120';
    case MealDelivery = '130';
    case LaundryDelivery = '140';
    case GroceryDelivery = '150';
    case ItAssistance = '160';
    case PetCare = '170';
    case HomeWatching = '180';
    case AdministrativeAssistance = '190';
    case ChildAccompanimentOverSix = '200';
    case RemoteAssistance = '210';
    case SignLanguageInterpreting = '220';
    case TemporaryDisabilityDriving = '230';
    case TemporaryDisabilityOutingAccompaniment = '240';
    case TemporaryDisabilityHomeHelp = '250';
    case CoordinationPlatform = '260';
    case Miscellaneous = '270';

    /**
     * @throws InvalidValue "nature_code.unknown" (ERR_CODE_NATURE)
     */
    public static function fromString(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::because('nature_code.unknown', 'Unknown nature code.');
    }

    /**
     * Only "miscellaneous" services (270) are not eligible to the tax credit nor to the aids.
     */
    public function isEligibleForTaxCredit(): bool
    {
        return $this !== self::Miscellaneous;
    }

    /**
     * True when the activity is attached to this nature (ERR_CODE_ACTIVITE_NATURE otherwise).
     */
    public function allowsActivity(ActivityCode $activity): bool
    {
        return $activity->nature() === $this;
    }
}
