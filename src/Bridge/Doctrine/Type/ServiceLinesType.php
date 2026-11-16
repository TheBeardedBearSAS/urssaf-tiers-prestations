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
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer\ServiceLinesSerializer;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;

/**
 * JSON column type of {@see ServiceLines}.
 */
final class ServiceLinesType extends Type
{
    public const NAME = 'urssaf_tps_service_lines';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof ServiceLines) {
            throw InvalidType::new($value, self::class, [ServiceLines::class, 'null']);
        }

        return JsonConversion::encode(ServiceLinesSerializer::toArray($value));
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ServiceLines
    {
        return JsonConversion::decode(
            $value,
            self::class,
            ServiceLines::class,
            ServiceLinesSerializer::fromArray(...),
        );
    }
}
