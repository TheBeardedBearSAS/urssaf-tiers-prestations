<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine\Support;

/**
 * Stands for an ORM configuration that implements the native lazy objects.
 */
final class NativeLazyOrmConfiguration
{
    public function enableNativeLazyObjects(bool $enabled): bool
    {
        return $enabled;
    }
}
