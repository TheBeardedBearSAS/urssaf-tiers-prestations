<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipality;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthPlace;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\DepartmentCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Validates a raw birth place. Paths: countryCode, departmentCode, municipalityCode, municipalityName.
 *
 * Born in France: department and municipality are required ("birth_place.department_required",
 * "birth_place.municipality_code_required", "birth_place.municipality_name_required").
 * Born abroad: they are forbidden ("birth_place.department_forbidden", "birth_place.municipality_code_forbidden",
 * "birth_place.municipality_name_forbidden").
 *
 * @implements Validator<BirthPlaceDraft>
 */
final readonly class BirthPlaceValidator implements Validator
{
    /**
     * Path => violation code fragment of the fields that depend on the country.
     */
    private const FRENCH_FIELDS = [
        'departmentCode' => 'department',
        'municipalityCode' => 'municipality_code',
        'municipalityName' => 'municipality_name',
    ];

    /**
     * @param BirthPlaceDraft $subject
     */
    public function validate(object $subject): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($subject, $collector);

        return $collector->result();
    }

    /**
     * Returns the birth place, or null after reporting the violations to the collector.
     */
    public function parse(BirthPlaceDraft $draft, ViolationCollector $collector): ?BirthPlace
    {
        $country = $collector->required('countryCode', 'country_code', $draft->countryCode, CountryCode::fromString(...));
        if ($country !== null && !$country->isFrance()) {
            return $this->reportForbidden($draft, $collector) ? null : BirthPlace::abroad($country);
        }
        $department = $collector->optional('departmentCode', $draft->departmentCode, DepartmentCode::fromString(...));
        $code = $collector->optional('municipalityCode', $draft->municipalityCode, BirthMunicipalityCode::fromString(...));
        $name = $collector->optional('municipalityName', $draft->municipalityName, MunicipalityName::ofBirthPlace(...));
        if ($country === null) {
            return null;
        }
        foreach (self::fields($draft) as $path => $raw) {
            if ($raw !== null && $raw !== '') {
                continue;
            }
            $collector->add(Violation::error(
                $path,
                \sprintf('birth_place.%s_required', self::FRENCH_FIELDS[$path]),
                'The birth department and municipality are required for a birth in France.',
            ));
        }
        if ($department === null || $code === null || $name === null) {
            return null;
        }

        return $collector->attempt(
            'municipalityCode',
            static fn(): BirthPlace => BirthPlace::inFrance($department, BirthMunicipality::of($code, $name)),
        );
    }

    /**
     * @return bool true when at least one forbidden field is filled in
     */
    private function reportForbidden(BirthPlaceDraft $draft, ViolationCollector $collector): bool
    {
        $reported = false;
        foreach (self::fields($draft) as $path => $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $collector->add(Violation::error(
                $path,
                \sprintf('birth_place.%s_forbidden', self::FRENCH_FIELDS[$path]),
                'The birth department and municipality must be empty for a birth abroad.',
            ));
            $reported = true;
        }

        return $reported;
    }

    /**
     * @return array{departmentCode: ?string, municipalityCode: ?string, municipalityName: ?string}
     */
    private static function fields(BirthPlaceDraft $draft): array
    {
        return [
            'departmentCode' => $draft->departmentCode,
            'municipalityCode' => $draft->municipalityCode,
            'municipalityName' => $draft->municipalityName,
        ];
    }
}
