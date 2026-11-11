<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * URSSAF integrated the payment request (accepted by method 050 or found by reconciliation).
 */
final readonly class PaymentRequestSubmitted implements DomainEvent
{
    public function __construct(
        private LocalPaymentRequestId $paymentRequestId,
        private TenantId $tenantId,
        private PaymentRequestId $urssafPaymentRequestId,
        private Instant $occurredAt,
    ) {}

    public function paymentRequestId(): LocalPaymentRequestId
    {
        return $this->paymentRequestId;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function urssafPaymentRequestId(): PaymentRequestId
    {
        return $this->urssafPaymentRequestId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
