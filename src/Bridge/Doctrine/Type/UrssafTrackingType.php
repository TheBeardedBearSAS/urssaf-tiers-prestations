<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer\TrackingSerializer;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafTracking;

/**
 * JSON column type of {@see UrssafTracking}.
 */
final class UrssafTrackingType extends Type
{
    public const NAME = 'urssaf_tps_urssaf_tracking';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof UrssafTracking) {
            throw InvalidType::new($value, self::class, [UrssafTracking::class, 'null']);
        }

        return JsonConversion::encode(TrackingSerializer::toArray($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UrssafTracking
    {
        return JsonConversion::decode(
            $value,
            self::class,
            UrssafTracking::class,
            TrackingSerializer::fromArray(...),
        );
    }
}
