<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Typed, defensive view over a decoded JSON document. This is the only place where untyped wire data is read:
 * every accessor either returns a well-typed value or throws {@see UnexpectedUrssafResponse}.
 *
 * One accessor per JSON shape read from URSSAF, hence the method count and complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class Payload
{
    /**
     * @param array<array-key, array<array-key, mixed>|scalar|null> $data
     */
    private function __construct(private array $data) {}

    /**
     * @throws UnexpectedUrssafResponse when the decoded JSON is neither an object nor an array
     */
    public static function fromDecoded(mixed $decoded): self
    {
        if (!\is_array($decoded)) {
            throw UnexpectedUrssafResponse::invalidPayload('body is neither a JSON object nor a JSON array');
        }

        return self::wrap($decoded);
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON object or array
     */
    private static function wrap(array $data): self
    {
        /** @var array<array-key, array<array-key, mixed>|scalar|null> $typed */
        $typed = $data;

        return new self($typed);
    }

    public function isList(): bool
    {
        return array_is_list($this->data);
    }

    /**
     * String value of a key. Null when absent, null or of another type.
     */
    public function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * Code value of a key: a string, or an integer as its decimal string (URSSAF sends some codes either way). Null
     * when absent, null or of another type.
     */
    public function code(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_int($value) ? (string) $value : $this->string($key);
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not a non-empty string
     */
    public function requireString(string $key): string
    {
        $value = $this->string($key) ?? '';

        if ($value === '') {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or empty', $key));
        }

        return $value;
    }

    /**
     * JSON number of a key. Null when absent.
     *
     * @throws UnexpectedUrssafResponse when present not a number
     */
    public function number(string $key): int|float|null
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_int($value) || \is_float($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a number', $key));
    }

    /**
     * Nested JSON object. Null when absent.
     *
     * @throws UnexpectedUrssafResponse when present but not an object
     */
    public function object(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!\is_array($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not an object', $key));
        }

        return self::wrap($value);
    }

    /**
     * Nested list of objects. Empty when absent.
     *
     * @return list<self>
     *
     * @throws UnexpectedUrssafResponse when present but not a list of objects
     */
    public function items(string $key): array
    {
        return $this->object($key)?->entries() ?? [];
    }

    /**
     * Elements of a top-level JSON array, each of which must be an object.
     *
     * @return list<self>
     *
     * @throws UnexpectedUrssafResponse when this payload is not a list of objects
     */
    public function entries(): array
    {
        if (!array_is_list($this->data)) {
            throw UnexpectedUrssafResponse::invalidPayload('expected a JSON array');
        }

        return array_map(
            static fn(array|bool|float|int|string|null $entry): self => \is_array($entry)
                ? self::wrap($entry)
                : throw UnexpectedUrssafResponse::invalidPayload('expected a JSON array of objects'),
            $this->data,
        );
    }
}
