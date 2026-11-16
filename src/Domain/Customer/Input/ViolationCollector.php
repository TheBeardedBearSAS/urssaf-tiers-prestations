<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input;

use Closure;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Parses raw fields into value objects and accumulates every violation (notification pattern).
 *
 * A raw value that is null or "" is missing.
 *
 * @internal
 */
final class ViolationCollector
{
    private ValidationResult $result;

    public function __construct()
    {
        $this->result = ValidationResult::valid();
    }

    /**
     * Reports "<subject>.required" when the value is missing.
     *
     * @template T of object
     *
     * @param Closure(string): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when missing or invalid
     */
    public function required(string $path, string $subject, ?string $raw, Closure $factory): ?object
    {
        if ($raw === null || $raw === '') {
            $this->add(Violation::error(
                $path,
                $subject . '.required',
                \sprintf('The %s is required.', str_replace('_', ' ', $subject)),
            ));

            return null;
        }

        return $this->attempt($path, static fn() => $factory($raw));
    }

    /**
     * @template T of object
     *
     * @param Closure(string): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when missing or invalid
     */
    public function optional(string $path, ?string $raw, Closure $factory): ?object
    {
        return $raw === null || $raw === '' ? null : $this->attempt($path, static fn() => $factory($raw));
    }

    /**
     * Runs the factory and reports its {@see InvalidValue} at the given path.
     *
     * @template T of object
     *
     * @param Closure(): T $factory
     *
     * @param-immediately-invoked-callable $factory
     *
     * @return T|null null when invalid
     */
    public function attempt(string $path, Closure $factory): ?object
    {
        try {
            return $factory();
        } catch (InvalidValue $invalidValue) {
            $this->add($invalidValue->toViolation($path));

            return null;
        }
    }

    public function add(Violation $violation): void
    {
        $this->result = $this->result->with($violation);
    }

    public function merge(ValidationResult $result, string $pathPrefix): void
    {
        $this->result = $this->result->merge($result->withPathPrefix($pathPrefix));
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }

}
