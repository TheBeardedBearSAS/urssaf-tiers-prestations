<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Column type of {@see Money}: the amount in cents, as a big integer (no floating point, no rounding).
 */
final class MoneyType extends Type
{
    public const NAME = 'urssaf_tps_money';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBigIntTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof Money) {
            throw InvalidType::new($value, self::class, [Money::class, 'null']);
        }

        return $value->cents();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Money
    {
        if ($value === null) {
            return null;
        }
        // Drivers return big integers as strings (PostgreSQL, MySQL with emulated prepares) or as integers (SQLite).
        if (!\is_int($value) && (!\is_string($value) || preg_match('/^\d+$/D', $value) !== 1)) {
            throw InvalidType::new($value, self::class, ['int', 'numeric-string', 'null']);
        }
        try {
            return Money::fromCents((int) $value);
        } catch (InvalidValue $exception) {
            throw InvalidFormat::new((string) $value, self::class, 'amount in cents', $exception);
        }
    }
}
