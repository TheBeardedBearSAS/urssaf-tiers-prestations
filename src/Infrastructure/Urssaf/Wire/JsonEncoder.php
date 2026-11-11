<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use JsonException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;

/**
 * Minimal JSON encoder able to emit {@see JsonDecimal} literals exactly. Floats are refused on purpose:
 * amounts must be wrapped in a JsonDecimal so no precision can be lost.
 *
 * A PHP list (including the empty array) is encoded as a JSON array and any other array as an object; wrap a map in
 * a {@see JsonObject} so that it is encoded as an object even when empty.
 */
final class JsonEncoder
{
    private const FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * @param array<array-key, mixed>|bool|int|string|JsonDecimal|JsonObject|null $value
     *
     * @throws InvariantViolation "json_encoder.unsupported_value" for floats and objects other than JsonDecimal and
     *                            JsonObject
     * @throws JsonException      for invalid UTF-8 strings
     */
    public static function encode(array|bool|int|string|JsonDecimal|JsonObject|null $value): string
    {
        if ($value instanceof JsonDecimal) {
            return $value->literal();
        }

        if ($value instanceof JsonObject) {
            return self::object($value->members());
        }

        if (!\is_array($value)) {
            return self::literal($value);
        }

        if (array_is_list($value)) {
            return '[' . implode(',', array_map(self::encodeItem(...), $value)) . ']';
        }

        return self::object($value);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function object(array $value): string
    {
        return '{' . implode(',', array_map(
            static fn(int|string $key, mixed $item): string => self::literal((string) $key)
                . ':'
                . self::encodeItem($item),
            array_keys($value),
            $value,
        )) . '}';
    }

    private static function literal(bool|int|string|null $value): string
    {
        return \strval(json_encode($value, self::FLAGS));
    }

    private static function encodeItem(mixed $item): string
    {
        if ($item === null || \is_scalar($item) && !\is_float($item) || \is_array($item) || $item instanceof JsonDecimal || $item instanceof JsonObject) {
            return self::encode($item);
        }

        throw InvariantViolation::because(
            'json_encoder.unsupported_value',
            'Only null, bool, int, string, arrays, JsonDecimal and JsonObject can be encoded.',
        );
    }
}
