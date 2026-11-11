<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;

final class SequentialIdGenerator implements IdGenerator
{
    private int $next = 1;

    public function __construct(private readonly string $prefix = 'id-') {}

    public function generate(): string
    {
        return $this->prefix . $this->next++;
    }
}
