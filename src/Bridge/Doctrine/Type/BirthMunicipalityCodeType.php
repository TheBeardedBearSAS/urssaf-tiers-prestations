<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipalityCode;

/**
 * Column type of {@see BirthMunicipalityCode}: a VARCHAR holding its value.
 */
final class BirthMunicipalityCodeType extends Type
{
    public const NAME = 'urssaf_tps_birth_municipality_code';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, BirthMunicipalityCode::class, self::class, static fn(BirthMunicipalityCode $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BirthMunicipalityCode
    {
        return StringValueConversion::toPhp($value, self::class, BirthMunicipalityCode::fromString(...));
    }
}
