<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

use Throwable;

interface TransactionManager
{
    /**
     * Runs the operation in a transaction: committed when it returns, rolled back (and the throwable rethrown
     * unchanged) when it throws.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     *
     * @throws Throwable
     */
    public function transactional(callable $operation): mixed;
}
