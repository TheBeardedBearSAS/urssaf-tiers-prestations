<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\Conversion;

use Closure;
use Doctrine\DBAL\Types\Exception\InvalidFormat;
use Doctrine\DBAL\Types\Exception\InvalidType;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Conversion shared by the column types of string-backed value objects: stored as their string value, rebuilt through
 * the named constructor of the value object, so that an invalid stored value is reported instead of silently accepted.
 */
final class StringValueConversion
{
    /**
     * @template T of object
     *
     * @param class-string<T>    $class
     * @param class-string       $type     the column type, for the error message
     * @param Closure(T): string $toString the string value of the value object
     * @param-immediately-invoked-callable $toString
     */
    public static function toDatabase(mixed $value, string $class, string $type, Closure $toString): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!$value instanceof $class) {
            throw InvalidType::new($value, $type, [$class, 'null']);
        }

        return $toString($value);
    }

    /**
     * @template T of object
     *
     * @param class-string       $type    the column type, for the error message
     * @param Closure(string): T $factory the named constructor of the value object
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null
     */
    public static function toPhp(mixed $value, string $type, Closure $factory): ?object
    {
        if ($value === null) {
            return null;
        }
        if (!\is_string($value)) {
            throw InvalidType::new($value, $type, ['string', 'null']);
        }
        try {
            return $factory($value);
        } catch (InvalidValue $exception) {
            throw InvalidFormat::new($value, $type, 'valid value', $exception);
        }
    }
}
