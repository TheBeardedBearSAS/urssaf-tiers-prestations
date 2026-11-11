<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * URSSAF accepted the registration (method 010): the customer must now accept the pairing (state awaiting_pairing).
 */
final readonly class CustomerSubmittedToUrssaf implements DomainEvent
{
    public function __construct(
        private RegistrationId $registrationId,
        private TenantId $tenantId,
        private UrssafCustomerId $urssafCustomerId,
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

    public function urssafCustomerId(): UrssafCustomerId
    {
        return $this->urssafCustomerId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
