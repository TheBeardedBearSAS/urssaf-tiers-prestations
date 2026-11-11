<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CountryCode;

/**
 * Birth place of the customer (lieuNaissance).
 *
 * - born in France (country 99100): department and municipality are required;
 * - born abroad: department and municipality are forbidden.
 *
 * For Paris, Lyon and Marseille the arrondissement must be given, not the whole city (75056, 69123, 13055):
 * "birth_place.arrondissement_required". The municipality is kept as its code and name (both null when born abroad).
 */
final readonly class BirthPlace
{
    /**
     * Department => municipality code of the cities that must be given by arrondissement.
     */
    private const CITIES_WITH_ARRONDISSEMENTS = ['075' => '056', '069' => '123', '013' => '055'];

    private function __construct(
        private CountryCode $country,
        private ?DepartmentCode $department,
        private ?BirthMunicipalityCode $municipalityCode,
        private ?MunicipalityName $municipalityName,
    ) {}

    /**
     * @throws InvalidValue "birth_place.arrondissement_required"
     */
    public static function inFrance(DepartmentCode $department, BirthMunicipality $municipality): self
    {
        if ((self::CITIES_WITH_ARRONDISSEMENTS[$department->value()] ?? null) === $municipality->code()->value()) {
            throw InvalidValue::because(
                'birth_place.arrondissement_required',
                'Invalid birth_place: for Paris, Lyon and Marseille give the arrondissement, not the whole city.',
            );
        }

        return new self(CountryCode::france(), $department, $municipality->code(), $municipality->name());
    }

    /**
     * @throws InvalidValue "birth_place.department_required" when the country is France
     */
    public static function abroad(CountryCode $country): self
    {
        if ($country->isFrance()) {
            throw InvalidValue::because(
                'birth_place.department_required',
                'Invalid birth_place: a birth in France requires a department and a municipality.',
            );
        }

        return new self($country, null, null, null);
    }

    /**
     * Birth in France from the 5-character INSEE municipality code: "69101" gives department "069" and municipality
     * "101", "2B072" gives "02B" / "072", "97411" gives "974" / "011".
     *
     * @throws InvalidValue
     */
    public static function fromInseeMunicipality(string $inseeCode, string $municipalityName): self
    {
        $code = MunicipalityCode::fromString($inseeCode);

        return self::inFrance(
            $code->birthDepartmentCode(),
            BirthMunicipality::of($code->birthMunicipalityCode(), MunicipalityName::ofBirthPlace($municipalityName)),
        );
    }

    public function country(): CountryCode
    {
        return $this->country;
    }

    public function isInFrance(): bool
    {
        return $this->country->isFrance();
    }

    /**
     * Null when born abroad.
     */
    public function department(): ?DepartmentCode
    {
        return $this->department;
    }

    /**
     * Null when born abroad.
     */
    public function municipality(): ?BirthMunicipality
    {
        if (!$this->municipalityCode instanceof BirthMunicipalityCode) {
            return null;
        }
        /** @var MunicipalityName $name set together with the code by the named constructors */
        $name = $this->municipalityName;

        return BirthMunicipality::of($this->municipalityCode, $name);
    }

    public function equals(self $other): bool
    {
        return $this->country->equals($other->country)
            && $this->department?->value() === $other->department?->value()
            && $this->municipalityCode?->value() === $other->municipalityCode?->value()
            && $this->municipalityName?->value() === $other->municipalityName?->value();
    }
}
