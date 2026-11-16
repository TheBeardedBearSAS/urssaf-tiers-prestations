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
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\ServiceIds;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Environment;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;

/**
 * The documented configuration reads the environment from an environment variable: it is resolved at runtime.
 */
final class EnvironmentPlaceholderTest extends TestCase
{
    public function testTheEnvironmentMayComeFromAnEnvironmentVariable(): void
    {
        $_ENV['URSSAF_TEST_ENVIRONMENT'] = 'production';
        $_SERVER['URSSAF_TEST_ENVIRONMENT'] = 'production';
        try {
            $configuration = Harness::boot(['environment' => '%env(URSSAF_TEST_ENVIRONMENT)%'])
                ->privateService(ServiceIds::API_CONFIGURATION, ApiConfiguration::class);
        } finally {
            unset($_ENV['URSSAF_TEST_ENVIRONMENT'], $_SERVER['URSSAF_TEST_ENVIRONMENT']);
        }

        static::assertSame(Environment::Production, $configuration->environment());
    }
}
