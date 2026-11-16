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
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * URSSAF refused the registration for a business reason (state rejected, final).
 */
final readonly class CustomerRegistrationRejected implements DomainEvent
{
    /**
     * @param list<RejectionCause> $causes
     */
    public function __construct(
        private RegistrationId $registrationId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
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
     * The reference of the record in the host application.
     */
    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
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
