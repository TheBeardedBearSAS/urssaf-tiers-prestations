<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\MunicipalityCode;

/**
 * Column type of {@see MunicipalityCode}: a VARCHAR holding its value.
 */
final class MunicipalityCodeType extends Type
{
    public const NAME = 'urssaf_tps_municipality_code';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, MunicipalityCode::class, self::class, static fn(MunicipalityCode $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?MunicipalityCode
    {
        return StringValueConversion::toPhp($value, self::class, MunicipalityCode::fromString(...));
    }
}
