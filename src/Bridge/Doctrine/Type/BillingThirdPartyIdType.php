<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\BillingThirdPartyId;

/**
 * Column type of {@see BillingThirdPartyId}: a VARCHAR holding its string value.
 */
final class BillingThirdPartyIdType extends Type
{
    public const NAME = 'urssaf_tps_billing_third_party_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, BillingThirdPartyId::class, self::class, static fn(BillingThirdPartyId $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BillingThirdPartyId
    {
        return StringValueConversion::toPhp($value, self::class, BillingThirdPartyId::fromString(...));
    }
}
