<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

/**
 * Why the last registration attempt was refused or failed: the error code (e.g. ERR_DGFIP_R2P_INCONNU,
 * ERREUR_TECHNIQUE) and its message, as reported by URSSAF or by the transport.
 */
final readonly class RejectionCause
{
    private function __construct(private string $code, private string $message) {}

    /**
     * @throws InvalidValue "rejection_cause_code.empty"
     */
    public static function of(string $code, string $message): self
    {
        if (trim($code) === '') {
            throw InvalidValue::empty('rejection_cause_code');
        }

        return new self($code, $message);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }
}
