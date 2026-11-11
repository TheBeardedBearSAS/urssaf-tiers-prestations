<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;

/**
 * Date-time columns that are always read and written in UTC, whatever the default time zone of the PHP process or the
 * time zone of the value (Doctrine's own date-time types use the process time zone, which would shift every instant).
 */
final class UtcDateTimeConversion
{
    public static function format(DateTimeInterface $value, AbstractPlatform $platform): string
    {
        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format($platform->getDateTimeFormatString());
    }

    /**
     * @param class-string $type the column type, for the error message
     */
    public static function parse(mixed $value, string $type, AbstractPlatform $platform): DateTimeImmutable
    {
        if (!\is_string($value)) {
            throw InvalidType::new($value, $type, ['string', 'null']);
        }
        $format = $platform->getDateTimeFormatString();

        $parsed = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));
        if ($parsed === false) {
            throw InvalidFormat::new($value, $type, $format);
        }

        return $parsed;
    }
}
