<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event;

use DateTimeImmutable;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * URSSAF refused the payment request with business errors (method 050); it will not be retried as is.
 */
final readonly class PaymentRequestRejectedByUrssaf implements DomainEvent
{
    /**
     * @param list<SubmissionError> $errors
     */
    public function __construct(
        private LocalPaymentRequestId $paymentRequestId,
        private TenantId $tenantId,
        private ExternalReference $externalReference,
        private array $errors,
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

    /**
     * The reference of the record in the host application.
     */
    public function externalReference(): ExternalReference
    {
        return $this->externalReference;
    }

    /**
     * @return list<SubmissionError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt->toDateTimeImmutable();
    }
}
