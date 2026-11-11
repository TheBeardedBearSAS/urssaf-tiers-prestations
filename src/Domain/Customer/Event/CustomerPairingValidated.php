<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * URSSAF reported the pairing as accepted (APPAREILLAGE_VALIDE): payment requests may be sent (state registered).
 */
final readonly class CustomerPairingValidated implements DomainEvent
{
    public function __construct(
        private RegistrationId $registrationId,
        private TenantId $tenantId,
        private Instant $pairingAcceptedAt,
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

    public function pairingAcceptedAt(): Instant
    {
        return $this->pairingAcceptedAt;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
