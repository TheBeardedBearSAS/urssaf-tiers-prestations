<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Collects the violations found while parsing a draft: parsing failures become violations at the given path and the
 * parsed value is then null.
 *
 * @internal
 */
final class DraftReader
{
    private ValidationResult $result;

    public function __construct()
    {
        $this->result = ValidationResult::valid();
    }

    /**
     * Missing value: "field.required" violation.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $parse
     *
     * @param callable(string): T $parse
     *
     * @return T|null
     */
    public function required(string $path, ?string $value, callable $parse): mixed
    {
        if ($value === null) {
            $this->missing($path);

            return null;
        }

        return $this->attempt($path, static fn(): mixed => $parse($value));
    }

    /**
     * Missing or blank value: null without violation.
     *
     * @template T
     *
     * @param-immediately-invoked-callable $parse
     *
     * @param callable(string): T $parse
     *
     * @return T|null
     */
    public function optional(string $path, ?string $value, callable $parse): mixed
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return $this->attempt($path, static fn(): mixed => $parse($value));
    }

    /**
     * @template T
     *
     * @param-immediately-invoked-callable $build
     *
     * @param callable(): T $build
     *
     * @return T|null
     */
    public function attempt(string $path, callable $build): mixed
    {
        try {
            return $build();
        } catch (InvalidValue $exception) {
            $this->merge(ValidationResult::of($exception->toViolation($path)));

            return null;
        }
    }

    public function missing(string $path): void
    {
        $this->merge(ValidationResult::of(Violation::error($path, 'field.required', 'This field is required.')));
    }

    public function merge(ValidationResult $result): void
    {
        $this->result = $this->result->merge($result);
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}
