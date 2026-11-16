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

/**
 * Cross-field rules of the street part of a postal address, shared by {@see StreetAddress} (fail fast) and the
 * input validator (collects every violation).
 *
 * The rules only depend on which fields are filled in.
 *
 * @phpstan-type Presence array{
 *     streetNumber: bool,
 *     streetLetter: bool,
 *     streetTypeCode: bool,
 *     streetName: bool,
 *     complement: bool,
 *     locality: bool,
 * }
 */
enum StreetAddressRule
{
    /** lettreVoie requires numeroVoie. */
    case LetterRequiresNumber;

    /** numeroVoie requires libelleVoie. */
    case NumberRequiresStreetName;

    /** libelleVoie requires codeTypeVoie. */
    case StreetNameRequiresStreetType;

    /** codeTypeVoie requires libelleVoie. */
    case StreetTypeRequiresStreetName;

    /** At least one of libelleVoie, complement, lieuDit. */
    case DeliveryPointRequired;

    /**
     * @param Presence $presence
     */
    public function isViolatedBy(array $presence): bool
    {
        return match ($this) {
            self::LetterRequiresNumber => $presence['streetLetter'] && !$presence['streetNumber'],
            self::NumberRequiresStreetName => $presence['streetNumber'] && !$presence['streetName'],
            self::StreetNameRequiresStreetType => $presence['streetName'] && !$presence['streetTypeCode'],
            self::StreetTypeRequiresStreetName => $presence['streetTypeCode'] && !$presence['streetName'],
            self::DeliveryPointRequired => !$presence['streetName'] && !$presence['complement'] && !$presence['locality'],
        };
    }

    /**
     * Path of the missing field, relative to the address ("" = the address itself).
     */
    public function path(): string
    {
        return match ($this) {
            self::LetterRequiresNumber => 'streetNumber',
            self::NumberRequiresStreetName, self::StreetTypeRequiresStreetName => 'streetName',
            self::StreetNameRequiresStreetType => 'streetTypeCode',
            self::DeliveryPointRequired => '',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::LetterRequiresNumber => 'street_number.required_with_street_letter',
            self::NumberRequiresStreetName => 'street_name.required_with_street_number',
            self::StreetNameRequiresStreetType => 'street_type_code.required_with_street_name',
            self::StreetTypeRequiresStreetName => 'street_name.required_with_street_type_code',
            self::DeliveryPointRequired => 'postal_address.delivery_point_required',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::LetterRequiresNumber => 'A street number is required when a street letter is given.',
            self::NumberRequiresStreetName => 'A street name is required when a street number is given.',
            self::StreetNameRequiresStreetType => 'A street type code is required when a street name is given.',
            self::StreetTypeRequiresStreetName => 'A street name is required when a street type code is given.',
            self::DeliveryPointRequired => 'At least one of the street name, the complement or the locality is required.',
        };
    }

    /**
     * @param Presence $presence
     *
     * @return list<self>
     */
    public static function violatedBy(array $presence): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn(self $rule): bool => $rule->isViolatedBy($presence),
        ));
    }
}
