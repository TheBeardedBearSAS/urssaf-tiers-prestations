<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\Iban;

/**
 * Column type of {@see Iban}: a VARCHAR holding its normalised value.
 */
final class IbanType extends Type
{
    public const NAME = 'urssaf_tps_iban';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, Iban::class, self::class, static fn(Iban $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Iban
    {
        return StringValueConversion::toPhp($value, self::class, Iban::fromString(...));
    }
}
