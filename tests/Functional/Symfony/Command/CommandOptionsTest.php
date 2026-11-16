<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;

final class CommandOptionsTest extends TestCase
{
    private const COMMAND = 'urssaf:customers:submit-pending';

    public function testRefusesALimitThatIsNotAPositiveInteger(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        foreach (['0', 'abc', '-1', '1.5'] as $limit) {
            static::assertSame(1, $tester->execute(['--limit' => $limit]), $limit);
            static::assertStringContainsString('The --limit option must be a positive integer.', $tester->getDisplay());
        }
    }

    public function testRefusesAnInvalidTenant(): void
    {
        $tester = Harness::boot()->command(self::COMMAND);

        static::assertSame(1, $tester->execute(['--tenant' => str_repeat('x', 65)]));
        static::assertStringContainsString('tenant_id', $tester->getDisplay());
    }

    public function testNeedsATenantWhenNoneIsConfigured(): void
    {
        $tester = Harness::boot(['credentials' => null, 'credentials_provider' => 'test.credentials'])->command(self::COMMAND);

        static::assertSame(1, $tester->execute([]));
        static::assertStringContainsString('No tenant to process: use --tenant=<id> or configure "urssaf_tiers_prestations.tenants".', $tester->getDisplay());
        static::assertSame(0, $tester->execute(['--tenant' => 'tenant-1']));
    }
}
