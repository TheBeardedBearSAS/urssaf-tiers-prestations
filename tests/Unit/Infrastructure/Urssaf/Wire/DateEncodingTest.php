<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\DateEncoding;

final class DateEncodingTest extends TestCase
{
    public function testItEncodesCalendarDatesAsMidnightUtc(): void
    {
        static::assertSame('1980-03-29T00:00:00Z', DateEncoding::encodeCalendarDate(CalendarDate::fromString('1980-03-29')));
    }

    public function testItEncodesInstantsInUtc(): void
    {
        $instant = Instant::fromIso8601('2022-02-01T17:40:00+01:00');

        static::assertSame('2022-02-01T16:40:00Z', DateEncoding::encodeInstant($instant));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function calendarDates(): iterable
    {
        yield 'zulu' => ['1980-03-29T00:00:00Z', '1980-03-29'];
        yield 'milliseconds' => ['1980-03-29T00:00:00.000Z', '1980-03-29'];
        yield 'offset' => ['1980-03-29T00:00:00+02:00', '1980-03-29'];
        yield 'plain date' => ['1980-03-29', '1980-03-29'];
    }

    #[DataProvider('calendarDates')]
    public function testItDecodesCalendarDatesTolerantly(string $wire, string $expected): void
    {
        static::assertSame($expected, DateEncoding::decodeCalendarDate($wire)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCalendarDates(): iterable
    {
        yield 'empty' => [''];
        yield 'french format' => ['29/03/1980'];
        yield 'text before' => ['on 1980-03-29'];
        yield 'text after the date' => ['1980-03-29 10:00'];
        yield 'impossible date' => ['2023-02-31T00:00:00Z'];
    }

    #[DataProvider('invalidCalendarDates')]
    public function testItRejectsInvalidCalendarDates(string $wire): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        DateEncoding::decodeCalendarDate($wire);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function instants(): iterable
    {
        yield 'zulu' => ['2022-02-01T16:40:00Z', '2022-02-01T16:40:00Z'];
        yield 'milliseconds' => ['2022-02-01T16:40:00.000Z', '2022-02-01T16:40:00Z'];
        yield 'offset' => ['2022-02-01T17:40:00+01:00', '2022-02-01T16:40:00Z'];
        yield 'plain date' => ['2022-02-01', '2022-02-01T00:00:00Z'];
    }

    #[DataProvider('instants')]
    public function testItDecodesInstantsTolerantly(string $wire, string $expected): void
    {
        static::assertSame($expected, DateEncoding::decodeInstant($wire)->toIso8601());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInstants(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['yesterday'];
        yield 'date with trailing text' => ['2022-02-01 noon'];
    }

    #[DataProvider('invalidInstants')]
    public function testItRejectsInvalidInstants(string $wire): void
    {
        $this->expectException(UnexpectedUrssafResponse::class);

        DateEncoding::decodeInstant($wire);
    }
}
