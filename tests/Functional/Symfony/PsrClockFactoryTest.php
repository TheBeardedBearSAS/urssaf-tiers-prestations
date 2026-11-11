<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\PsrClockFactory;

final class PsrClockFactoryTest extends TestCase
{
    public function testTheClockOfTheApplicationIsUsedWhenThereIsOne(): void
    {
        $clock = new MockClock();

        static::assertSame($clock, PsrClockFactory::create($clock));
    }

    public function testTheSystemClockIsTheFallback(): void
    {
        static::assertInstanceOf(NativeClock::class, PsrClockFactory::create(null));
    }
}
