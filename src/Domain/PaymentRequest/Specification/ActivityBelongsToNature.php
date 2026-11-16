<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * The optional activity must be attached to the nature of the line (ERR_CODE_ACTIVITE_NATURE).
 *
 * Violation: "line.activity_not_in_nature" at "activityCode".
 *
 * @implements Validator<ServiceLine>
 */
final readonly class ActivityBelongsToNature implements Validator
{
    /**
     * @param ServiceLine $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $activity = $subject->activityCode();
        if ($activity === null || $subject->natureCode()->allowsActivity($activity)) {
            return ValidationResult::valid();
        }

        return ValidationResult::of(Violation::error(
            'activityCode',
            'line.activity_not_in_nature',
            'The activity is not attached to the nature of the line.',
            ['nature_code' => $subject->natureCode()->value, 'activity_code' => $activity->value],
        ));
    }
}
