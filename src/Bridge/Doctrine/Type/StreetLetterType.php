<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\StreetLetter;

/**
 * Column type of {@see StreetLetter}: a VARCHAR holding its value.
 */
final class StreetLetterType extends Type
{
    public const NAME = 'urssaf_tps_street_letter';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, StreetLetter::class, self::class, static fn(StreetLetter $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?StreetLetter
    {
        return StringValueConversion::toPhp($value, self::class, StreetLetter::fromString(...));
    }
}
