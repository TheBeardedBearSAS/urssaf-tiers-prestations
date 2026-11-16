<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Contract;

/**
 * Records whether an instance was ever unserialized (its __wakeup() ran).
 */
final class WakeUpRecorder
{
    private static bool $wokenUp = false;

    public function __wakeup(): void
    {
        self::$wokenUp = true;
    }

    public static function wokenUp(): bool
    {
        return self::$wokenUp;
    }
}
