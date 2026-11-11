<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\DepartmentCode;

/**
 * Column type of {@see DepartmentCode}: a VARCHAR holding its value.
 */
final class DepartmentCodeType extends Type
{
    public const NAME = 'urssaf_tps_department_code';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, DepartmentCode::class, self::class, static fn(DepartmentCode $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DepartmentCode
    {
        return StringValueConversion::toPhp($value, self::class, DepartmentCode::fromString(...));
    }
}
