<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityName;

/**
 * Column type of the municipality name of a birth place ({@see MunicipalityName::ofBirthPlace()}): a VARCHAR holding its value.
 */
final class BirthMunicipalityNameType extends Type
{
    public const NAME = 'urssaf_tps_birth_municipality_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, MunicipalityName::class, self::class, static fn(MunicipalityName $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MunicipalityName
    {
        return StringValueConversion::toPhp($value, self::class, MunicipalityName::ofBirthPlace(...));
    }
}
