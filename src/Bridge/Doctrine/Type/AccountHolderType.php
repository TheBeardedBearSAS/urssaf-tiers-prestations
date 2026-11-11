<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\StringValueConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\AccountHolder;

/**
 * Column type of {@see AccountHolder}: a VARCHAR holding its value.
 */
final class AccountHolderType extends Type
{
    public const NAME = 'urssaf_tps_account_holder';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return StringValueConversion::toDatabase($value, AccountHolder::class, self::class, static fn(AccountHolder $value): string => $value->value());
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AccountHolder
    {
        return StringValueConversion::toPhp($value, self::class, AccountHolder::fromString(...));
    }
}
