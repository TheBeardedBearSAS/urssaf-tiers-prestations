<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafStatusChange;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * A poll (method 070) observed a status different from the previous one.
 */
final readonly class PaymentRequestStatusChanged implements DomainEvent
{
    public function __construct(
        private LocalPaymentRequestId $paymentRequestId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private ?UrssafStatusChange $previous,
        private UrssafStatusChange $current,
    ) {}

    public function paymentRequestId(): LocalPaymentRequestId
    {
        return $this->paymentRequestId;
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
     * Null when no status was known before.
     */
    public function previous(): ?UrssafStatusChange
    {
        return $this->previous;
    }

    public function current(): UrssafStatusChange
    {
        return $this->current;
    }

    /**
     * The instant the new status was observed.
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->current->observedAt()->toDateTimeImmutable();
    }
}
