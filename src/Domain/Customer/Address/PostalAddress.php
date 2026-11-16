<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;

/**
 * Postal address of the customer (adressePostale).
 */
final readonly class PostalAddress
{
    private function __construct(
        private StreetAddress $street,
        private MunicipalityName $municipalityName,
        private MunicipalityCode $municipalityCode,
        private PostalCode $postalCode,
        private CountryCode $countryCode,
    ) {}

    public static function of(
        StreetAddress $street,
        MunicipalityName $municipalityName,
        MunicipalityCode $municipalityCode,
        PostalCode $postalCode,
        CountryCode $countryCode,
    ): self {
        return new self($street, $municipalityName, $municipalityCode, $postalCode, $countryCode);
    }

    public function street(): StreetAddress
    {
        return $this->street;
    }

    public function municipalityName(): MunicipalityName
    {
        return $this->municipalityName;
    }

    public function municipalityCode(): MunicipalityCode
    {
        return $this->municipalityCode;
    }

    public function postalCode(): PostalCode
    {
        return $this->postalCode;
    }

    public function countryCode(): CountryCode
    {
        return $this->countryCode;
    }

    public function equals(self $other): bool
    {
        return $this->street->equals($other->street)
            && $this->municipalityName->equals($other->municipalityName)
            && $this->municipalityCode->equals($other->municipalityCode)
            && $this->postalCode->equals($other->postalCode)
            && $this->countryCode->equals($other->countryCode);
    }
}
