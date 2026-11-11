<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

use PHPUnit\Framework\Assert;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Locks of the test application held "by another process".
 */
final class HeldLocks
{
    private function __construct() {}

    public static function factory(Harness $harness): LockFactory
    {
        return $harness->privateService('lock.factory', LockFactory::class);
    }

    /**
     * The caller must keep the returned lock: it is released when the object is destroyed.
     */
    public static function hold(Harness $harness, string $resource): LockInterface
    {
        $lock = self::factory($harness)->createLock($resource);
        Assert::assertTrue($lock->acquire());

        return $lock;
    }
}
