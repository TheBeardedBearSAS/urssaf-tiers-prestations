<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

/**
 * Column type of {@see PaymentRequestId}: a VARCHAR holding its string value.
 */
final class PaymentRequestIdType extends Type
{
    public const NAME = 'urssaf_tps_payment_request_id';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, PaymentRequestId::class, self::class, static fn(PaymentRequestId $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PaymentRequestId
    {
        return StringValueConversion::toPhp($value, self::class, PaymentRequestId::fromString(...));
    }
}
