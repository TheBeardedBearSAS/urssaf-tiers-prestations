<?php

declare(strict_types=1);

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
