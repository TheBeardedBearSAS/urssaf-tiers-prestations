<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Id;

use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\RandomBytesSource;

/**
 * Random bytes fake returning the given bytes and recording the requested lengths.
 */
final class FixedRandomBytes implements RandomBytesSource
{
    /** @var list<int> */
    private array $requested = [];

    public function __construct(private readonly string $bytes) {}

    public function bytes(int $length): string
    {
        $this->requested[] = $length;

        return $this->bytes;
    }

    /**
     * @return list<int>
     */
    public function requested(): array
    {
        return $this->requested;
    }
}
