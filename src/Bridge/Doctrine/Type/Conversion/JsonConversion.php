<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion;

use Closure;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use JsonException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Conversion shared by the JSON column types: an explicit, hand-written serializer (see the Serializer namespace) turns
 * the domain value into an array and back, rebuilding it through its named constructors.
 */
final class JsonConversion
{
    private const DEPTH = 512;

    /**
     * @param array<array-key, mixed> $document
     */
    public static function encode(array $document): string
    {
        return json_encode($document, \JSON_THROW_ON_ERROR);
    }

    /**
     * @template T
     *
     * @param class-string                        $type     the column type, for the error message
     * @param string                              $expected description of the rebuilt value, for the error message
     * @param Closure(array<array-key, mixed>): T $rebuild  the serializer
     * @param-immediately-invoked-callable $rebuild
     *
     * @return T|null
     */
    public static function decode(mixed $value, string $type, string $expected, Closure $rebuild): mixed
    {
        if ($value === null) {
            return null;
        }
        if (!\is_string($value)) {
            throw InvalidType::new($value, $type, ['string', 'null']);
        }
        try {
            // @mago-expect analysis:mixed-assignment The decoded JSON is untyped; it is checked to be an array below.
            $document = json_decode($value, true, self::DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw InvalidFormat::new($value, $type, 'JSON', $exception);
        }
        if (!\is_array($document)) {
            throw InvalidFormat::new($value, $type, 'JSON object or array');
        }
        try {
            return $rebuild($document);
        } catch (InvalidValue $exception) {
            throw InvalidFormat::new($value, $type, $expected, $exception);
        }
    }
}
