<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Shared;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

final class InstantTest extends TestCase
{
    public function testFromDateTimeNormalisesToUtcAndTruncatesMicroseconds(): void
    {
        $instant = Instant::fromDateTime(new DateTimeImmutable('2022-02-01 17:40:00.987654', new DateTimeZone('Europe/Paris')));

        static::assertSame('2022-02-01T16:40:00Z', $instant->toIso8601());
        static::assertSame('2022-02-01T16:40:00Z', (string) $instant);
        static::assertSame('UTC', $instant->toDateTimeImmutable()->getTimezone()->getName());
        static::assertSame('2022-02-01 16:40:00.000000', $instant->toDateTimeImmutable()->format('Y-m-d H:i:s.u'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideIsoStrings(): iterable
    {
        yield 'zulu' => ['2022-02-01T16:40:00Z', '2022-02-01T16:40:00Z'];
        yield 'milliseconds' => ['1980-03-29T00:00:00.000Z', '1980-03-29T00:00:00Z'];
        yield 'microseconds' => ['1980-03-29T00:00:00.123456Z', '1980-03-29T00:00:00Z'];
        yield 'positive offset' => ['2022-02-01T17:40:00+01:00', '2022-02-01T16:40:00Z'];
        yield 'negative offset' => ['2022-02-01T11:40:00-05:00', '2022-02-01T16:40:00Z'];
    }

    #[DataProvider('provideIsoStrings')]
    public function testFromIso8601(string $value, string $expected): void
    {
        static::assertSame($expected, Instant::fromIso8601($value)->toIso8601());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidIsoStrings(): iterable
    {
        yield 'no offset' => ['2022-02-01T16:40:00', 'instant.invalid_format'];
        yield 'date only' => ['2022-02-01', 'instant.invalid_format'];
        yield 'space separator' => ['2022-02-01 16:40:00Z', 'instant.invalid_format'];
        yield 'prefix' => ['x2022-02-01T16:40:00Z', 'instant.invalid_format'];
        yield 'trailing newline' => ["2022-02-01T16:40:00Z\n", 'instant.invalid_format'];
        yield 'too many fraction digits' => ['2022-02-01T16:40:00.1234567Z', 'instant.invalid_format'];
        yield 'rolled over day' => ['2022-02-30T16:40:00Z', 'instant.invalid_date'];
        yield 'rolled over minute' => ['2022-02-01T16:60:00Z', 'instant.invalid_date'];
        yield 'unparseable month' => ['2022-13-01T16:40:00Z', 'instant.invalid_date'];
    }

    #[DataProvider('provideInvalidIsoStrings')]
    public function testFromIso8601RejectsInvalidInput(string $value, string $code): void
    {
        try {
            Instant::fromIso8601($value);
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame($code, $exception->violationCode());
        }
    }

    public function testInvalidMessages(): void
    {
        try {
            Instant::fromIso8601('2022-02-30T16:40:00Z');
            static::fail('Expected exception');
        } catch (InvalidValue $exception) {
            static::assertSame('Invalid instant: the date does not exist.', $exception->getMessage());
        }
        $this->expectExceptionObject(InvalidValue::invalidFormat('instant', 'ISO 8601 date-time with offset'));

        Instant::fromIso8601('nope');
    }

    public function testTimeZoneViews(): void
    {
        $instant = Instant::fromIso8601('2022-01-31T23:30:00Z');
        $paris = new DateTimeZone('Europe/Paris');

        static::assertSame('2022-02-01T00:30:00+01:00', $instant->inTimeZone($paris)->format(DATE_ATOM));
        static::assertSame('2022-02-01', $instant->calendarDate($paris)->toString());
        static::assertSame('2022-01-31', $instant->calendarDate(new DateTimeZone('UTC'))->toString());
    }

    public function testComparisons(): void
    {
        $instant = Instant::fromIso8601('2022-02-01T16:40:00Z');
        $earlier = Instant::fromIso8601('2022-02-01T16:39:59Z');
        $later = Instant::fromIso8601('2022-02-01T16:40:01Z');
        $same = Instant::fromIso8601('2022-02-01T17:40:00.500+01:00');

        static::assertTrue($instant->isAfter($earlier));
        static::assertFalse($instant->isAfter($same));
        static::assertFalse($instant->isAfter($later));
        static::assertTrue($instant->isBefore($later));
        static::assertFalse($instant->isBefore($same));
        static::assertFalse($instant->isBefore($earlier));
        static::assertTrue($instant->equals($same));
        static::assertFalse($instant->equals($later));
        static::assertFalse($instant->equals($earlier));
    }
}
