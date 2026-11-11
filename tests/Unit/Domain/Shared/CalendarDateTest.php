<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;

final class CalendarDateTest extends TestCase
{
    public function testFromString(): void
    {
        $date = CalendarDate::fromString('1980-03-29');

        static::assertSame('1980-03-29', $date->toString());
        static::assertSame('1980-03-29', (string) $date);
        static::assertSame(1980, $date->year());
        static::assertSame(3, $date->month());
        static::assertSame(12, CalendarDate::fromString('0001-12-31')->month());
        static::assertSame(1, CalendarDate::fromString('0001-12-31')->year());
        static::assertSame('2024-02-29', CalendarDate::fromString('2024-02-29')->toString());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidStrings(): iterable
    {
        yield 'empty' => ['', 'calendar_date.invalid_format'];
        yield 'with time' => ['1980-03-29T00:00:00Z', 'calendar_date.invalid_format'];
        yield 'prefix' => ['x1980-03-29', 'calendar_date.invalid_format'];
        yield 'trailing newline' => ["1980-03-29\n", 'calendar_date.invalid_format'];
        yield 'short month' => ['1980-3-29', 'calendar_date.invalid_format'];
        yield 'french format' => ['29/03/1980', 'calendar_date.invalid_format'];
        yield 'year zero' => ['0000-01-01', 'calendar_date.invalid_date'];
        yield 'month 13' => ['1980-13-01', 'calendar_date.invalid_date'];
        yield 'month 0' => ['1980-00-01', 'calendar_date.invalid_date'];
        yield 'day 0' => ['1980-01-00', 'calendar_date.invalid_date'];
        yield 'not leap' => ['2023-02-29', 'calendar_date.invalid_date'];
    }

    #[DataProvider('provideInvalidStrings')]
    public function testFromStringRejectsInvalidInput(string $value, string $code): void
    {
        try {
            CalendarDate::fromString($value);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testInvalidDateMessage(): void
    {
        $this->expectExceptionObject(
            InvalidValue::because('calendar_date.invalid_date', 'Invalid calendar_date: the date does not exist.'),
        );

        CalendarDate::fromString('2023-02-30');
    }

    public function testFromDateTimeUsesTheGivenTimeZone(): void
    {
        $instant = new DateTimeImmutable('2022-01-31T23:30:00Z');

        static::assertSame('2022-01-31', CalendarDate::fromDateTime($instant, new DateTimeZone('UTC'))->toString());
        static::assertSame('2022-02-01', CalendarDate::fromDateTime($instant, new DateTimeZone('Europe/Paris'))->toString());
    }

    public function testFromDateTimeDoesNotMutateMutableInput(): void
    {
        $dateTime = new DateTime('2022-01-31T23:30:00Z');

        static::assertSame('2022-02-01', CalendarDate::fromDateTime($dateTime, new DateTimeZone('Europe/Paris'))->toString());
        static::assertSame('Z', $dateTime->getTimezone()->getName());
    }

    public function testComparisons(): void
    {
        $date = CalendarDate::fromString('2022-02-15');
        $earlier = CalendarDate::fromString('2022-02-14');
        $later = CalendarDate::fromString('2022-02-16');
        $same = CalendarDate::fromString('2022-02-15');

        static::assertTrue($date->isAfter($earlier));
        static::assertFalse($date->isAfter($same));
        static::assertFalse($date->isAfter($later));
        static::assertTrue($date->isBefore($later));
        static::assertFalse($date->isBefore($same));
        static::assertFalse($date->isBefore($earlier));
        static::assertTrue($date->equals($same));
        static::assertFalse($date->equals($later));
        static::assertTrue(CalendarDate::fromString('2021-12-31')->isBefore(CalendarDate::fromString('2022-01-01')));
    }

    public function testIsSameMonthAs(): void
    {
        $date = CalendarDate::fromString('2022-02-15');

        static::assertTrue($date->isSameMonthAs(CalendarDate::fromString('2022-02-01')));
        static::assertTrue($date->isSameMonthAs(CalendarDate::fromString('2022-02-28')));
        static::assertFalse($date->isSameMonthAs(CalendarDate::fromString('2022-03-15')));
        static::assertFalse($date->isSameMonthAs(CalendarDate::fromString('2023-02-15')));
        static::assertFalse($date->isSameMonthAs(CalendarDate::fromString('2022-12-15')));
    }
}
