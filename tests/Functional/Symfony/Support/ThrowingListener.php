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

use LogicException;

/**
 * An event listener that always fails.
 */
final class ThrowingListener
{
    public function __invoke(): never
    {
        throw new LogicException('listener exploded');
    }
}
