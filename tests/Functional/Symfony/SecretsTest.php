<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsNotSerializable;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;

final class SecretsTest extends TestCase
{
    public function testTheContainerDumpOnlyShowsTheEnvironmentPlaceholderOfTheSecret(): void
    {
        $dump = (new TestKernel())->containerAsYaml();

        static::assertStringNotContainsString(TestKernel::SECRET, $dump);
        static::assertStringContainsString('URSSAF_TEST_SECRET', $dump);
    }

    public function testTheCredentialsMaskTheirSecretWhenDumped(): void
    {
        $provider = Harness::boot()->privateService(CredentialsProvider::class, CredentialsProvider::class);
        $credentials = $provider->forTenant(TenantId::fromString('tenant-1'));

        static::assertSame(TestKernel::SECRET, $credentials->clientSecret());
        ob_start();
        // @mago-expect lint:no-debug-symbols The dump of the credentials is exactly what is under test.
        var_dump($provider, $credentials);
        $dump = (string) ob_get_clean();
        static::assertStringContainsString('client-1', $dump);
        static::assertStringNotContainsString(TestKernel::SECRET, $dump);
        static::assertStringNotContainsString(TestKernel::SECRET, print_r($credentials, true));

        $this->expectException(CredentialsNotSerializable::class);

        serialize($credentials);
    }
}
