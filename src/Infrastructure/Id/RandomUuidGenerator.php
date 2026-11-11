<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id;

use Random\RandomException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;

/**
 * RFC 4122 version 4 UUIDs (lower-case), built from random bytes without external libraries.
 */
final readonly class RandomUuidGenerator implements IdGenerator
{
    private const BYTES = 16;

    public function __construct(private RandomBytesSource $randomBytes) {}

    /**
     * @return non-empty-string
     *
     * @throws RandomException when no secure source of randomness is available
     */
    public function generate(): string
    {
        $bytes = $this->randomBytes->bytes(self::BYTES);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8)
            . '-' . substr($hex, 8, 4)
            . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4)
            . '-' . substr($hex, 20);
    }
}
