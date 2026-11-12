<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * URSSAF reported a pairing status that does not allow transmission (in progress, not finalised, customer blocked,
 * mandate expired) and the registration state changed accordingly.
 */
final readonly class CustomerPairingStatusChanged implements DomainEvent
{
    public function __construct(
        private RegistrationId $registrationId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private PairingStatus $pairingStatus,
        private RegistrationState $previousState,
        private RegistrationState $newState,
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

    public function pairingStatus(): PairingStatus
    {
        return $this->pairingStatus;
    }

    public function previousState(): RegistrationState
    {
        return $this->previousState;
    }

    public function newState(): RegistrationState
    {
        return $this->newState;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
