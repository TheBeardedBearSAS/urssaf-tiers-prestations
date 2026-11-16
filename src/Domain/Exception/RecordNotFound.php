<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Exception;

/**
 * Thrown when a record looked up by identifier does not exist.
 *
 * Violation code: "<record_type>.not_found" (e.g. "customer_registration.not_found").
 */
final class RecordNotFound extends DomainException
{
    public static function withIdentifier(string $recordType, string $identifier): self
    {
        return new self(
            \sprintf('No %s found for identifier "%s".', $recordType, $identifier),
            $recordType . '.not_found',
            ['record_type' => $recordType, 'identifier' => $identifier],
        );
    }

    public function recordType(): string
    {
        return (string) $this->params()['record_type'];
    }

    public function identifier(): string
    {
        return (string) $this->params()['identifier'];
    }
}
