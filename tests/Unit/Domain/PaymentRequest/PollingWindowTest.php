<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;

final class PollingWindowTest extends TestCase
{
    public function testDefaults(): void
    {
        $window = new PollingWindow();

        static::assertSame(90, $window->settledMaxAge()->d);
        static::assertSame(30, $window->unknownStatusMaxAge()->d);
        static::assertSame(1, $window->maxPollingAge()->y);
    }

    public function testLimits(): void
    {
        $settled = new DateInterval('P1D');
        $unknown = new DateInterval('P2D');
        $polling = new DateInterval('P3D');
        $window = new PollingWindow($settled, $unknown, $polling);

        static::assertSame($settled, $window->settledMaxAge());
        static::assertSame($unknown, $window->unknownStatusMaxAge());
        static::assertSame($polling, $window->maxPollingAge());
    }

    public function testTheLimitIsExclusive(): void
    {
        $since = Fixtures::at(Fixtures::NOW);
        $age = new DateInterval('PT1H');

        static::assertTrue(PollingWindow::isWithin($since, $age, Fixtures::at('2022-03-01T00:59:59Z')));
        static::assertFalse(PollingWindow::isWithin($since, $age, Fixtures::at('2022-03-01T01:00:00Z')));
    }
}
