<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Id;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\SecureRandomBytes;

final class RandomUuidGeneratorTest extends TestCase
{
    private const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testGeneratesLowerCaseVersion4Uuids(): void
    {
        $generator = new RandomUuidGenerator(new SecureRandomBytes());

        for ($i = 0; $i < 1000; ++$i) {
            static::assertMatchesRegularExpression(self::UUID_V4, $generator->generate());
        }
    }

    public function testGeneratesDistinctIdentifiers(): void
    {
        $generator = new RandomUuidGenerator(new SecureRandomBytes());

        static::assertNotSame($generator->generate(), $generator->generate());
    }

    public function testSetsVersionAndVariantBits(): void
    {
        $generator = new RandomUuidGenerator(new FixedRandomBytes(str_repeat("\xFF", 16)));

        static::assertSame('ffffffff-ffff-4fff-bfff-ffffffffffff', $generator->generate());
    }

    public function testClearsRandomBitsThatMustBeZero(): void
    {
        $generator = new RandomUuidGenerator(new FixedRandomBytes(str_repeat("\x00", 16)));

        static::assertSame('00000000-0000-4000-8000-000000000000', $generator->generate());
    }

    public function testKeepsTheRandomBytesInTheirPositions(): void
    {
        $bytes = '';
        for ($i = 0; $i < 16; ++$i) {
            $bytes .= \chr($i * 16 + $i);
        }

        $generator = new RandomUuidGenerator(new FixedRandomBytes($bytes));

        static::assertSame('00112233-4455-4677-8899-aabbccddeeff', $generator->generate());
    }

    public function testRequestsSixteenRandomBytes(): void
    {
        $source = new FixedRandomBytes(str_repeat("\x01", 16));

        (new RandomUuidGenerator($source))->generate();

        static::assertSame([16], $source->requested());
    }
}
