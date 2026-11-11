<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Fake;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;

final class SequenceIdGenerator implements IdGenerator
{
    private int $counter = 0;

    /**
     * @return non-empty-string
     */
    public function generate(): string
    {
        ++$this->counter;

        return 'request-id-' . $this->counter;
    }
}
