<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Birth municipality of a customer born in France (communeNaissance): 3-digit code and label.
 */
final readonly class BirthMunicipality
{
    private function __construct(private BirthMunicipalityCode $code, private MunicipalityName $name) {}

    public static function of(BirthMunicipalityCode $code, MunicipalityName $name): self
    {
        return new self($code, $name);
    }

    /**
     * Derives the 3-digit code from the 5-character INSEE municipality code (use the arrondissement code for
     * Paris, Lyon and Marseille): "69101" gives "101", "2B072" gives "072", "97411" gives "011".
     *
     * @throws InvalidValue "municipality_code.invalid_format" or a "birth_municipality_name.*" code
     */
    public static function fromInseeCode(string $inseeCode, string $name): self
    {
        return self::of(
            MunicipalityCode::fromString($inseeCode)->birthMunicipalityCode(),
            MunicipalityName::ofBirthPlace($name),
        );
    }

    public function code(): BirthMunicipalityCode
    {
        return $this->code;
    }

    public function name(): MunicipalityName
    {
        return $this->name;
    }

    public function equals(self $other): bool
    {
        return $this->code->equals($other->code) && $this->name->equals($other->name);
    }
}
