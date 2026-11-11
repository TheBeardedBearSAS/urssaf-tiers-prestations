<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer\RejectionCausesSerializer;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion\JsonConversion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;

/**
 * JSON column type of a list of {@see RejectionCause}.
 */
final class RejectionCausesType extends Type
{
    public const NAME = 'urssaf_tps_rejection_causes';

    private const EXPECTED = 'list<' . RejectionCause::class . '>';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidType::new($value, self::class, [self::EXPECTED, 'null']);
        }
        // @mago-expect analysis:mixed-assignment The list comes from an untyped column value; each item is checked below.
        foreach ($value as $cause) {
            if (!$cause instanceof RejectionCause) {
                throw InvalidType::new($value, self::class, [self::EXPECTED, 'null']);
            }
        }

        /** @var list<RejectionCause> $value */
        return JsonConversion::encode(RejectionCausesSerializer::toArray($value));
    }

    /**
     * @return list<RejectionCause>|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return JsonConversion::decode(
            $value,
            self::class,
            self::EXPECTED,
            RejectionCausesSerializer::fromArray(...),
        );
    }
}
