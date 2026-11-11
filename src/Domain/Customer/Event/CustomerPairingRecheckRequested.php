<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * URSSAF refused a payment request because the customer / provider link is missing: the registered customer is back
 * to awaiting_pairing until method 020 confirms the pairing again.
 */
final readonly class CustomerPairingRecheckRequested implements DomainEvent
{
    public function __construct(
        private RegistrationId $registrationId,
        private TenantId $tenantId,
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

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
