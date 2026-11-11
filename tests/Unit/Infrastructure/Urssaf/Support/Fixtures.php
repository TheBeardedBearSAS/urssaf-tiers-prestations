<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support;

use RuntimeException;

final class Fixtures
{
    private function __construct() {}

    public static function load(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../../../Fixtures/wire/' . $name . '.json');
        if ($content === false) {
            throw new RuntimeException('Missing fixture ' . $name);
        }

        return $content;
    }
}
