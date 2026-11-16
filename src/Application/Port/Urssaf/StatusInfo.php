<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

/**
 * Raw payment request status of method 070 ({"code": "70", "libelle": "Payée"}).
 */
final readonly class StatusInfo
{
    private function __construct(private string $code, private string $label) {}

    public static function of(string $code, string $label): self
    {
        return new self($code, $label);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }
}
