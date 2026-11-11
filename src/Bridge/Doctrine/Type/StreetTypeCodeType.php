<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetTypeCode;

/**
 * Column type of {@see StreetTypeCode}: a VARCHAR holding its value.
 */
final class StreetTypeCodeType extends Type
{
    public const NAME = 'urssaf_tps_street_type_code';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, StreetTypeCode::class, self::class, static fn(StreetTypeCode $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?StreetTypeCode
    {
        return StringValueConversion::toPhp($value, self::class, StreetTypeCode::fromString(...));
    }
}
