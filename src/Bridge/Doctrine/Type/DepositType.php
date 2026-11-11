<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer\DepositSerializer;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;

/**
 * JSON column type of an optional {@see Deposit}: a null deposit is stored as SQL NULL, so the all-nullable group never needs an embeddable (which Doctrine cannot hydrate as null).
 */
final class DepositType extends Type
{
    public const NAME = 'urssaf_tps_deposit';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof Deposit) {
            throw InvalidType::new($value, self::class, [Deposit::class, 'null']);
        }

        return JsonConversion::encode(DepositSerializer::toArray($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Deposit
    {
        return JsonConversion::decode(
            $value,
            self::class,
            Deposit::class,
            DepositSerializer::fromArray(...),
        );
    }
}
