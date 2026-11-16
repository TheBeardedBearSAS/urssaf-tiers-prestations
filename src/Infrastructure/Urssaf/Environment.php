<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf;

/**
 * Target URSSAF platform. The sandbox host is not published in the public documentation, so it can be
 * overridden through {@see ApiConfiguration}.
 */
enum Environment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public function baseUri(): string
    {
        return match ($this) {
            self::Sandbox => 'https://api-edi.urssaf.fr',
            self::Production => 'https://api.urssaf.fr',
        };
    }
}
