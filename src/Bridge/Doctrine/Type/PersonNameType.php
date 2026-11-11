<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\PersonName;

/**
 * Column type of {@see PersonName} (birth name, usage name, first names: the three kinds share the same rules, so any of them is rebuilt with the same named constructor): a VARCHAR holding its value.
 */
final class PersonNameType extends Type
{
    public const NAME = 'urssaf_tps_person_name';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, PersonName::class, self::class, static fn(PersonName $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PersonName
    {
        return StringValueConversion::toPhp($value, self::class, PersonName::birthName(...));
    }
}
