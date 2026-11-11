<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use Exception;

/**
 * Column type of a {@see DateTimeZone}: its identifier (e.g. "Europe/Paris").
 */
final class TimeZoneType extends Type
{
    public const NAME = 'urssaf_tps_time_zone';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof DateTimeZone) {
            throw InvalidType::new($value, self::class, [DateTimeZone::class, 'null']);
        }

        return $value->getName();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTimeZone
    {
        if ($value === null) {
            return null;
        }
        if (!\is_string($value)) {
            throw InvalidType::new($value, self::class, ['string', 'null']);
        }
        try {
            return new DateTimeZone($value);
        } catch (Exception $exception) {
            throw InvalidFormat::new($value, self::class, 'time zone identifier', $exception);
        }
    }
}
