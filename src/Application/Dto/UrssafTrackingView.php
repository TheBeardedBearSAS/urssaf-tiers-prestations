<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;

/**
 * Read model of what URSSAF reported about a payment request (primitives only, null while unknown).
 * The status is the raw code (status code, or error code of a rejected submission); the label is set for known codes.
 */
final readonly class UrssafTrackingView
{
    private function __construct(
        public ?string $paymentRequestId,
        public ?string $status,
        public ?string $statusLabel,
        public ?string $lastStatusChangeAt,
        public ?string $lastPolledAt,
        public ?int $transferAmountCents,
        public ?string $transferValueDate,
        public ?string $rejectionCode,
        public ?string $rejectionComment,
    ) {}

    public static function fromPaymentRequest(PaymentRequest $paymentRequest): self
    {
        $tracking = $paymentRequest->tracking();
        $transfer = $tracking->transfer();
        $rejection = $tracking->rejection();

        return new self(
            $paymentRequest->paymentRequestId()?->value(),
            $paymentRequest->rawUrssafStatus(),
            $paymentRequest->urssafStatus()?->label(),
            $paymentRequest->lastStatusChangeAt()?->toIso8601(),
            $paymentRequest->lastPolledAt()?->toIso8601(),
            $transfer?->amount()->cents(),
            $transfer?->valueDate()->toString(),
            $rejection?->code(),
            $rejection?->comment(),
        );
    }
}
