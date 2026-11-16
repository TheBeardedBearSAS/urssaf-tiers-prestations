<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Records the configuration of an extension once every bundle has prepended its own (the container forgets it when
 * the compilation ends).
 */
final class ExtensionConfigSnapshot implements CompilerPassInterface
{
    /** @var list<array<array-key, mixed>> */
    private array $configs = [];

    public function __construct(private readonly string $extension) {}

    public function process(ContainerBuilder $container): void
    {
        $this->configs = array_values($container->getExtensionConfig($this->extension));
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function configs(): array
    {
        return $this->configs;
    }
}
