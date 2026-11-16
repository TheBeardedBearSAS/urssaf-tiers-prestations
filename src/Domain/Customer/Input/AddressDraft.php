<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input;

/**
 * Raw postal address input. The municipality code is the 5-character INSEE code.
 */
final readonly class AddressDraft
{
    public function __construct(
        public ?string $streetNumber,
        public ?string $streetLetter,
        public ?string $streetTypeCode,
        public ?string $streetName,
        public ?string $complement,
        public ?string $locality,
        public ?string $municipalityName,
        public ?string $municipalityCode,
        public ?string $postalCode,
        public ?string $countryCode,
    ) {}
}
