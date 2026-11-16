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

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\AddressText;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetAddressRule;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetLetter;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetTypeCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Validates a raw postal address: every field (paths streetNumber, streetLetter, streetTypeCode, streetName,
 * complement, locality, municipalityName, municipalityCode, postalCode, countryCode) and the cross-field rules of
 * {@see StreetAddressRule} (same rules as the {@see StreetAddress} constructor).
 *
 * @implements Validator<AddressDraft>
 */
final readonly class PostalAddressValidator implements Validator
{
    /**
     * @param AddressDraft $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($subject, $collector);

        return $collector->result();
    }

    /**
     * Returns the address, or null after reporting the violations to the collector.
     */
    public function parse(AddressDraft $draft, ViolationCollector $collector): ?PostalAddress
    {
        $street = $this->parseStreet($draft, $collector);
        $municipalityName = $collector->required(
            'municipalityName',
            'municipality_name',
            $draft->municipalityName,
            MunicipalityName::ofAddress(...),
        );
        $municipalityCode = $collector->required(
            'municipalityCode',
            'municipality_code',
            $draft->municipalityCode,
            MunicipalityCode::fromString(...),
        );
        $postalCode = $collector->required('postalCode', 'postal_code', $draft->postalCode, PostalCode::fromString(...));
        $country = $collector->required('countryCode', 'country_code', $draft->countryCode, CountryCode::fromString(...));
        if (!$street instanceof StreetAddress || $municipalityName === null || $municipalityCode === null || $postalCode === null || $country === null) {
            return null;
        }

        return PostalAddress::of($street, $municipalityName, $municipalityCode, $postalCode, $country);
    }

    private function parseStreet(AddressDraft $draft, ViolationCollector $collector): ?StreetAddress
    {
        $before = \count($collector->result()->errors());
        $number = $collector->optional('streetNumber', $draft->streetNumber, StreetNumber::fromString(...));
        $letter = $collector->optional('streetLetter', $draft->streetLetter, StreetLetter::fromString(...));
        $typeCode = $collector->optional('streetTypeCode', $draft->streetTypeCode, StreetTypeCode::fromString(...));
        $name = $collector->optional('streetName', $draft->streetName, AddressText::streetName(...));
        $complement = $collector->optional('complement', $draft->complement, AddressText::complement(...));
        $locality = $collector->optional('locality', $draft->locality, AddressText::locality(...));
        foreach (StreetAddressRule::violatedBy([
            'streetNumber' => self::isFilled($draft->streetNumber),
            'streetLetter' => self::isFilled($draft->streetLetter),
            'streetTypeCode' => self::isFilled($draft->streetTypeCode),
            'streetName' => self::isFilled($draft->streetName),
            'complement' => self::isFilled($draft->complement),
            'locality' => self::isFilled($draft->locality),
        ]) as $rule) {
            $collector->add(Violation::error($rule->path(), $rule->code(), $rule->message()));
        }

        return \count($collector->result()->errors()) > $before
            ? null
            : new StreetAddress($number, $letter, $typeCode, $name, $complement, $locality);
    }

    private static function isFilled(?string $raw): bool
    {
        return $raw !== null && $raw !== '';
    }
}
