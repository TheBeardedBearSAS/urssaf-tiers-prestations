<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * The registration failed for a technical reason and may be retried (state error).
 */
final readonly class CustomerRegistrationFailed implements DomainEvent
{
    /**
     * @param list<RejectionCause> $causes
     */
    public function __construct(
        private RegistrationId $registrationId,
        private TenantId $tenantId,
        private array $causes,
        private Instant $occurredAt,
    ) {}

    public function registrationId(): RegistrationId
    {
        return $this->registrationId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * @return list<RejectionCause>
     */
    public function causes(): array
    {
        return $this->causes;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
