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
 * Optional activity refining a nature (codeActivite, api-reference §3.2). Backed by the official code.
 */
enum ActivityCode: string
{
    case DailyLivingAdministrativeFees = '30A001';
    case DailyLivingNight = '30A002';
    case DailyLivingWeekend = '30A003';
    case HouseworkAdministrativeFees = '60A001';
    case HouseworkNight = '60A002';
    case HouseworkWeekend = '60A003';
    case TutoringAdministrativeFees = '100A001';

    /**
     * @throws InvalidValue "activity_code.unknown" (ERR_CODE_ACTIVITE)
     */
    public static function fromString(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::because('activity_code.unknown', 'Unknown activity code.');
    }

    public function nature(): NatureCode
    {
        return match ($this) {
            self::DailyLivingAdministrativeFees, self::DailyLivingNight, self::DailyLivingWeekend => NatureCode::DailyLivingAssistance,
            self::HouseworkAdministrativeFees, self::HouseworkNight, self::HouseworkWeekend => NatureCode::Housework,
            self::TutoringAdministrativeFees => NatureCode::Tutoring,
        };
    }
}
