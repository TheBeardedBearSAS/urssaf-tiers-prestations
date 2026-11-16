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
 * Unit of a service line quantity (unite, api-reference §3.4). Backed by the official code.
 */
enum Unit: string
{
    case Hour = 'HEURE';
    case Flat = 'FORFAIT';

    /**
     * @throws InvalidValue "unit.unknown"
     */
    public static function fromString(string $code): self
    {
        return self::tryFrom($code) ?? throw InvalidValue::because('unit.unknown', 'Unknown unit.');
    }
}
