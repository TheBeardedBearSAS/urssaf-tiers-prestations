<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;

/**
 * Column type of {@see InvoiceNumber}: a VARCHAR holding its string value.
 */
final class InvoiceNumberType extends Type
{
    public const NAME = 'urssaf_tps_invoice_number';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, InvoiceNumber::class, self::class, static fn(InvoiceNumber $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?InvoiceNumber
    {
        return StringValueConversion::toPhp($value, self::class, InvoiceNumber::fromString(...));
    }
}
