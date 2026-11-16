<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;

/**
 * Designates a customer registration of a tenant, by registration id or by the host external reference.
 */
final readonly class CustomerSelector
{
    private function __construct(public RegistrationId|ExternalReference $reference) {}

    public static function byRegistrationId(RegistrationId $registrationId): self
    {
        return new self($registrationId);
    }

    public static function byExternalReference(ExternalReference $externalReference): self
    {
        return new self($externalReference);
    }
}
