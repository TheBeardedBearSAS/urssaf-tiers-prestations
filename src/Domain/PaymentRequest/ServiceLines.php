<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Ordered, immutable collection of the service lines of a payment request.
 *
 * It may be empty so that "at least one line" is reported as a violation ({@see Specification\AtLeastOneLine}).
 */
final readonly class ServiceLines
{
    /**
     * @param list<ServiceLine> $lines
     */
    private function __construct(private array $lines) {}

    public static function of(ServiceLine ...$lines): self
    {
        return new self(array_values($lines));
    }

    /**
     * @return list<ServiceLine>
     */
    public function all(): array
    {
        return $this->lines;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    public function totalInclTax(): Money
    {
        return array_reduce(
            $this->lines,
            static fn(Money $total, ServiceLine $line): Money => $total->add($line->amounts()->totalInclTax()),
            Money::fromCents(0),
        );
    }

    public function totalExclTax(): Money
    {
        return array_reduce(
            $this->lines,
            static fn(Money $total, ServiceLine $line): Money => $total->add($line->amounts()->totalExclTax()),
            Money::fromCents(0),
        );
    }

    /**
     * Same lines in the same order.
     */
    public function equals(self $other): bool
    {
        if (\count($this->lines) !== \count($other->lines)) {
            return false;
        }
        foreach ($this->lines as $index => $line) {
            if (!$line->equals($other->lines[$index])) {
                return false;
            }
        }

        return true;
    }
}
