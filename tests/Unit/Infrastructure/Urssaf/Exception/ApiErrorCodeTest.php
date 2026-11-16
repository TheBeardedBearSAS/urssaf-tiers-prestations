<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\ApiErrorCode;

final class ApiErrorCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rawCodes(): iterable
    {
        yield 'clean' => ['ERR_DATE_NAISSANCE_FUTUR', 'ERR_DATE_NAISSANCE_FUTUR'];
        yield 'surrounding spaces' => [' ERR_DATE_NAISSANCE_FUTUR ', 'ERR_DATE_NAISSANCE_FUTUR'];
        yield 'inner space' => ['ERR_ ACOMPTE_BLOQUE', 'ERR_ACOMPTE_BLOQUE'];
        yield 'tabs and newlines' => ["\tERR_CMPT\n_BLOQUE\r", 'ERR_CMPT_BLOQUE'];
        yield 'empty' => ['', ''];
    }

    #[DataProvider('rawCodes')]
    public function testItNormalisesWhitespace(string $raw, string $expected): void
    {
        static::assertSame($expected, ApiErrorCode::normalise($raw));
    }

    public function testFromCodeResolvesDirtyCodes(): void
    {
        static::assertSame(ApiErrorCode::DepositNotAuthorised, ApiErrorCode::fromCode('ERR_ ACOMPTE_BLOQUE'));
        static::assertSame(ApiErrorCode::BirthDateInFuture, ApiErrorCode::fromCode(' ERR_DATE_NAISSANCE_FUTUR '));
    }

    public function testFromCodeReturnsNullForUnknownCodes(): void
    {
        static::assertNull(ApiErrorCode::fromCode('ERR_SOMETHING_NEW'));
    }

    public function testOnlyTheTwoBlockedAccountCodesFlagABlockedCustomer(): void
    {
        $blocked = array_filter(
            ApiErrorCode::cases(),
            static fn(ApiErrorCode $code): bool => $code->isCustomerAccountBlocked(),
        );

        static::assertSame(
            [ApiErrorCode::RegistrationCustomerAccountBlocked, ApiErrorCode::CustomerAccountBlocked],
            array_values($blocked),
        );
    }

    public function testOnlyTheTechnicalErrorIsTechnical(): void
    {
        $technical = array_filter(ApiErrorCode::cases(), static fn(ApiErrorCode $code): bool => $code->isTechnical());

        static::assertSame([ApiErrorCode::TechnicalError], array_values($technical));
    }
}
