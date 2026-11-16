<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id;

use Random\RandomException;

/**
 * Source of cryptographically secure random bytes (a seam for deterministic tests of the UUID generator).
 */
interface RandomBytesSource
{
    /**
     * @param int<1, max> $length
     *
     * @throws RandomException when no secure source of randomness is available
     */
    public function bytes(int $length): string;
}
