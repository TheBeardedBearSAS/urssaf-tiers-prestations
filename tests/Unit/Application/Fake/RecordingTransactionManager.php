<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\TransactionManager;
use Throwable;

final class RecordingTransactionManager implements TransactionManager
{
    private int $committed = 0;

    public function __construct(private readonly Journal $journal) {}

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        $this->journal->add('begin');

        try {
            $result = $operation();
        } catch (Throwable $exception) {
            $this->journal->add('rollback');

            throw $exception;
        }
        $this->journal->add('commit');
        ++$this->committed;

        return $result;
    }

    public function committed(): int
    {
        return $this->committed;
    }
}
