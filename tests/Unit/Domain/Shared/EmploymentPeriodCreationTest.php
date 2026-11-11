<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

final class EmploymentPeriodCreationTest extends TestCase
{
    private static function period(string $start, string $end, string $timeZone = 'Europe/Paris'): EmploymentPeriod
    {
        return EmploymentPeriod::between(Instant::fromIso8601($start), Instant::fromIso8601($end), new DateTimeZone($timeZone));
    }

    public function testBetween(): void
    {
        $period = self::period('2022-02-01T16:40:00Z', '2022-02-01T16:45:00Z');

        static::assertSame('2022-02-01T16:40:00Z', $period->start()->toIso8601());
        static::assertSame('2022-02-01T16:45:00Z', $period->end()->toIso8601());
        static::assertSame('Europe/Paris', $period->businessTimeZone()->getName());
    }

    public function testInstantaneousPeriodIsAllowed(): void
    {
        $period = self::period('2022-02-01T16:40:00Z', '2022-02-01T16:40:00Z');

        static::assertTrue($period->start()->equals($period->end()));
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'employment_period.end_before_start',
            'Invalid employment_period: the end must not be before the start.',
        ));

        self::period('2022-02-01T16:40:00Z', '2022-02-01T16:39:59Z');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideMultiMonthPeriods(): iterable
    {
        yield 'distinct months everywhere' => ['2022-01-15T10:00:00Z', '2022-02-15T10:00:00Z', 'Europe/Paris'];
        yield 'same month in UTC, not in business time zone' => ['2022-01-31T22:00:00Z', '2022-01-31T23:30:00Z', 'Europe/Paris'];
        yield 'same month in business time zone, not in UTC' => ['2022-01-31T23:30:00Z', '2022-02-01T00:30:00Z', 'America/New_York'];
        yield 'same month number, different year' => ['2021-02-10T10:00:00Z', '2022-02-10T10:00:00Z', 'UTC'];
    }

    #[DataProvider('provideMultiMonthPeriods')]
    public function testPeriodsSpanningSeveralMonthsAreRejected(string $start, string $end, string $timeZone): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'employment_period.not_single_month',
            'Invalid employment_period: the start and the end must belong to the same calendar month.',
        ));

        self::period($start, $end, $timeZone);
    }

    public function testEquals(): void
    {
        $period = self::period('2022-02-01T16:40:00Z', '2022-02-01T16:45:00Z');

        static::assertTrue($period->equals(self::period('2022-02-01T16:40:00Z', '2022-02-01T16:45:00Z')));
        static::assertFalse($period->equals(self::period('2022-02-01T16:40:01Z', '2022-02-01T16:45:00Z')));
        static::assertFalse($period->equals(self::period('2022-02-01T16:40:00Z', '2022-02-01T16:45:01Z')));
        static::assertFalse($period->equals(self::period('2022-02-01T16:40:00Z', '2022-02-01T16:45:00Z', 'UTC')));
    }
}
