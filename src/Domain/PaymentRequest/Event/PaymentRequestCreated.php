<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * A payment request was validated locally and is pending submission.
 */
final readonly class PaymentRequestCreated implements DomainEvent
{
    public function __construct(
        private LocalPaymentRequestId $paymentRequestId,
        private TenantId $tenantId,
        private InvoiceNumber $invoiceNumber,
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

    public function invoiceNumber(): InvoiceNumber
    {
        return $this->invoiceNumber;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
