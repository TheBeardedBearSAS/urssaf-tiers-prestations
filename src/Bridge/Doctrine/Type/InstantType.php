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
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\UtcDateTimeConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Column type of {@see Instant}: a UTC date-time with second precision.
 */
final class InstantType extends Type
{
    public const NAME = 'urssaf_tps_instant';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTimeTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof Instant) {
            throw InvalidType::new($value, self::class, [Instant::class, 'null']);
        }

        return UtcDateTimeConversion::format($value->toDateTimeImmutable(), $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Instant
    {
        if ($value === null) {
            return null;
        }

        return Instant::fromDateTime(UtcDateTimeConversion::parse($value, self::class, $platform));
    }
}
