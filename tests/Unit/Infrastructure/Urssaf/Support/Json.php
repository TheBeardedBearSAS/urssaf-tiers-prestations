<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support;

use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\Payload;

final class Json
{
    private function __construct() {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function payload(array $data): Payload
    {
        return JsonDecoder::decode(json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function fixture(string $name): Payload
    {
        return JsonDecoder::decode(Fixtures::load($name));
    }
}
