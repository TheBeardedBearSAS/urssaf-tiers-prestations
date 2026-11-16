<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use LogicException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\UrssafException;

final class CredentialsNotSerializable extends LogicException implements UrssafException
{
    public static function create(): self
    {
        return new self('API credentials must never be serialized.');
    }
}
