<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Validation;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;

/**
 * Immutable, ordered collection of violations (notification pattern).
 *
 * A result is valid when it contains no error; warnings never invalidate it.
 */
final readonly class ValidationResult
{
    /**
     * @param list<Violation> $violations
     */
    private function __construct(private array $violations) {}

    public static function valid(): self
    {
        return new self([]);
    }

    public static function of(Violation ...$violations): self
    {
        return new self(array_values($violations));
    }

    public function with(Violation $violation): self
    {
        return new self([...$this->violations, $violation]);
    }

    public function merge(self $other): self
    {
        return new self([...$this->violations, ...$other->violations]);
    }

    /**
     * Nests every violation under the given parent path (see {@see Violation::withPathPrefix()}).
     */
    public function withPathPrefix(string $prefix): self
    {
        return new self(array_map(
            static fn(Violation $violation): Violation => $violation->withPathPrefix($prefix),
            $this->violations,
        ));
    }

    public function isValid(): bool
    {
        return $this->errors() === [];
    }

    /**
     * @return list<Violation>
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn(Violation $violation): bool => $violation->isError(),
        ));
    }

    /**
     * @return list<Violation>
     */
    public function warnings(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn(Violation $violation): bool => !$violation->isError(),
        ));
    }

    /**
     * @return list<Violation>
     */
    public function all(): array
    {
        return $this->violations;
    }

    /**
     * @throws ValidationFailed when the result contains at least one error
     */
    public function throwIfInvalid(): void
    {
        if (!$this->isValid()) {
            throw ValidationFailed::withResult($this);
        }
    }
}
