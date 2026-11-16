<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonDecimal;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonObject;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\MoneyEncoding;

final class JsonEncoderTest extends TestCase
{
    /**
     * @return iterable<string, array{array<array-key, mixed>|bool|int|string|JsonDecimal|null, string}>
     */
    public static function documents(): iterable
    {
        yield 'null' => [null, 'null'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'integer' => [12, '12'];
        yield 'string' => ['text', '"text"'];
        yield 'string with quote' => ['a"b', '"a\"b"'];
        yield 'slashes are not escaped' => ['a/b', '"a/b"'];
        yield 'unicode is not escaped' => ['Zoé', '"Zoé"'];
        yield 'empty array' => [[], '[]'];
        yield 'list' => [[1, 'a', null], '[1,"a",null]'];
        yield 'object' => [['a' => 1, 'b' => ['c' => true]], '{"a":1,"b":{"c":true}}'];
        yield 'object with quoted key' => [['a"b' => 1], '{"a\"b":1}'];
        yield 'object with integer keys' => [[1 => 'a', 3 => 'b'], '{"1":"a","3":"b"}'];
        yield 'nested lists' => [[[1], [2, 3]], '[[1],[2,3]]'];
    }

    /**
     * @param array<array-key, mixed>|bool|int|string|JsonDecimal|null $value
     */
    #[DataProvider('documents')]
    public function testItEncodesDocumentsExactly(array|bool|int|string|JsonDecimal|null $value, string $expected): void
    {
        static::assertSame($expected, JsonEncoder::encode($value));
    }

    public function testDecimalsAreEmittedVerbatimAlone(): void
    {
        static::assertSame('11.02', JsonEncoder::encode(JsonDecimal::fromString('11.02')));
    }

    public function testDecimalsAreEmittedVerbatimInsideDocuments(): void
    {
        static::assertSame('{"m":[0.5]}', JsonEncoder::encode(['m' => [JsonDecimal::fromString('0.5')]]));
    }

    public function testAmountsKeepTheirExactDecimalRepresentation(): void
    {
        $sum = Money::fromCents(10)->add(Money::fromCents(20));

        static::assertSame('[0.30]', JsonEncoder::encode([MoneyEncoding::encodeMoney($sum)]));
        static::assertSame('[11.02]', JsonEncoder::encode([MoneyEncoding::encodeMoney(Money::fromDecimalString('11.02'))]));
    }

    public function testJsonObjectsAreAlwaysObjects(): void
    {
        static::assertSame('{}', JsonEncoder::encode(JsonObject::of([])));
        static::assertSame('[]', JsonEncoder::encode([]));
        static::assertSame('{"a":{},"b":[]}', JsonEncoder::encode(JsonObject::of(['a' => JsonObject::of([]), 'b' => []])));
        static::assertSame('[{"k":1}]', JsonEncoder::encode([JsonObject::of(['k' => 1])]));
        static::assertSame(['k' => 1], JsonObject::of(['k' => 1])->members());
    }

    public function testFloatsAreRefused(): void
    {
        $this->expectException(InvariantViolation::class);

        JsonEncoder::encode([0.1]);
    }

    public function testForeignObjectsAreRefused(): void
    {
        $this->expectException(InvariantViolation::class);

        JsonEncoder::encode(['x' => new stdClass()]);
    }

    public function testInvalidUtf8IsRefused(): void
    {
        $this->expectException(JsonException::class);

        JsonEncoder::encode(["\xB1\x31"]);
    }
}
