<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Shared;

use Stringable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Identifier of the worker who performed a service (complement2): "SAP" + 9-digit SIREN (NOVA number)
 * or "SIR" + 14-digit SIRET.
 */
final readonly class IntervenantId implements Stringable
{
    private const SUBJECT = 'intervenant_id';
    private const NOVA_PATTERN = '/^SAP\d{9}$/D';
    private const SIRET_PATTERN = '/^SIR\d{14}$/D';

    private function __construct(private string $value) {}

    /**
     * @throws InvalidValue "intervenant_id.empty" (ERR_NUMINTERVENANT_MANQUANT) or "intervenant_id.invalid_format"
     *                      (ERR_FORMAT_NUMINTERVENANT)
     */
    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if (preg_match(self::NOVA_PATTERN, $value) !== 1 && preg_match(self::SIRET_PATTERN, $value) !== 1) {
            throw InvalidValue::invalidFormat(self::SUBJECT, 'SAP + 9 digits or SIR + 14 digits');
        }

        return new self($value);
    }

    public function isNova(): bool
    {
        return str_starts_with($this->value, 'SAP');
    }

    public function isSiret(): bool
    {
        return str_starts_with($this->value, 'SIR');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
