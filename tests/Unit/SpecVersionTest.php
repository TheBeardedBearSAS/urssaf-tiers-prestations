<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\SpecVersion;

final class SpecVersionTest extends TestCase
{
    public function testItExposesTheImplementedSpecificationVersion(): void
    {
        static::assertMatchesRegularExpression('/^1\.30\.19$/', SpecVersion::CURRENT);
    }
}
