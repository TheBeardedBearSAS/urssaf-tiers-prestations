<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestContext;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;

/**
 * A draft whose every required part was parsed; its result may still contain rule violations.
 *
 * @internal
 */
final readonly class CompleteDraft implements DraftReading
{
    public function __construct(
        private ValidationResult $result,
        private ExternalReference $reference,
        private Invoice $invoice,
    ) {}

    public function result(): ValidationResult
    {
        return $this->result;
    }

    public function toPaymentRequest(LocalPaymentRequestId $id, PaymentRequestContext $context): PaymentRequest
    {
        $this->result->throwIfInvalid();

        return PaymentRequest::create($id, $context->tenantId(), $this->reference, $this->invoice, $context->now());
    }
}
