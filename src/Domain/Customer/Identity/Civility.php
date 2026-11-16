<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Civility of the customer (civilite).
 */
enum Civility: string
{
    case Male = '1';
    case Female = '2';

    /**
     * @throws InvalidValue "civility.invalid_format"
     */
    public static function fromCode(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::invalidFormat('civility', '"1" (male) or "2" (female)');
    }
}
