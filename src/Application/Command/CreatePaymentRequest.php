<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Validates and stores a pending payment request for a registered customer (submitted later by
 * SubmitPaymentRequests). The external reference of the request is part of the draft.
 */
final readonly class CreatePaymentRequest
{
    public function __construct(
        public TenantId $tenantId,
        public CustomerSelector $customer,
        public PaymentRequestDraft $paymentRequest,
    ) {}
}
