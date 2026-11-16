<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Specification;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\EmploymentPeriodNotBeforePairing;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\EmploymentPeriodNotInFuture;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

final class ContextualSpecificationsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideNow(): iterable
    {
        yield 'after the end' => ['2022-02-01T16:45:01Z', []];
        yield 'exactly at the end' => [Fixtures::END, []];
        yield 'one second before the end' => ['2022-02-01T16:44:59Z', ['error employmentPeriod employment_period.in_future']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideNow')]
    public function testEmploymentPeriodNotInFuture(string $now, array $expected): void
    {
        $result = (new EmploymentPeriodNotInFuture(Fixtures::at($now)))->validate(Fixtures::validInvoice());

        static::assertSame($expected, ViolationAssertions::summary($result));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providePairing(): iterable
    {
        yield 'before the start' => ['2022-02-01T16:39:59Z', []];
        yield 'exactly at the start' => [Fixtures::START, []];
        yield 'one second after the start' => ['2022-02-01T16:40:01Z', ['error employmentPeriod employment_period.before_pairing']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providePairing')]
    public function testEmploymentPeriodNotBeforePairing(string $pairing, array $expected): void
    {
        $result = (new EmploymentPeriodNotBeforePairing(Fixtures::at($pairing)))->validate(Fixtures::validInvoice());

        static::assertSame($expected, ViolationAssertions::summary($result));
    }
}
