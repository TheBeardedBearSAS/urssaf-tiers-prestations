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

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Street part of a postal address: number, letter, type, name, complement and locality, all optional but
 * consistent (see {@see StreetAddressRule}).
 *
 * The public constructor is the factory (more than 5 parts).
 */
final readonly class StreetAddress
{
    /**
     * @throws InvalidValue with the code of the first broken {@see StreetAddressRule}
     */
    public function __construct(
        private ?StreetNumber $number,
        private ?StreetLetter $letter,
        private ?StreetTypeCode $typeCode,
        private ?AddressText $name,
        private ?AddressText $complement,
        private ?AddressText $locality,
    ) {
        $broken = StreetAddressRule::violatedBy([
            'streetNumber' => $number instanceof StreetNumber,
            'streetLetter' => $letter instanceof StreetLetter,
            'streetTypeCode' => $typeCode instanceof StreetTypeCode,
            'streetName' => $name instanceof AddressText,
            'complement' => $complement instanceof AddressText,
            'locality' => $locality instanceof AddressText,
        ]);
        if ($broken !== []) {
            throw InvalidValue::because($broken[0]->code(), $broken[0]->message());
        }
    }

    public function number(): ?StreetNumber
    {
        return $this->number;
    }

    public function letter(): ?StreetLetter
    {
        return $this->letter;
    }

    public function typeCode(): ?StreetTypeCode
    {
        return $this->typeCode;
    }

    public function name(): ?AddressText
    {
        return $this->name;
    }

    public function complement(): ?AddressText
    {
        return $this->complement;
    }

    public function locality(): ?AddressText
    {
        return $this->locality;
    }

    public function equals(self $other): bool
    {
        return $this->number?->value() === $other->number?->value()
            && $this->letter?->value() === $other->letter?->value()
            && $this->typeCode?->value() === $other->typeCode?->value()
            && $this->name?->value() === $other->name?->value()
            && $this->complement?->value() === $other->complement?->value()
            && $this->locality?->value() === $other->locality?->value();
    }
}
