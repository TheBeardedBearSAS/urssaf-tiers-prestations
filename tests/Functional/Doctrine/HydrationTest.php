<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Doctrine;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\Hydration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

final class HydrationTest extends TestCase
{
    public function testItKeepsOnlyTheInstancesOfTheExpectedClassInOrder(): void
    {
        $first = TenantId::fromString('tenant-1');
        $second = TenantId::fromString('tenant-2');

        $all = Hydration::all(new ArrayIterator([$first, 'text', 12, null, new stdClass(), $second]), TenantId::class);

        static::assertSame([$first, $second], $all);
    }

    public function testItReturnsTheFirstInstanceOrNull(): void
    {
        $first = TenantId::fromString('tenant-1');

        static::assertSame($first, Hydration::first(['text', $first, TenantId::fromString('tenant-2')], TenantId::class));
        static::assertNull(Hydration::first(['text'], TenantId::class));
        static::assertNull(Hydration::first([], TenantId::class));
    }
}
