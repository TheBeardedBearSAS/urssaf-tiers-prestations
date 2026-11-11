<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;

/**
 * Read model of a payment request (primitives only). Amounts in cents, dates "Y-m-d", instants ISO 8601 UTC.
 */
final readonly class PaymentRequestView
{
    /**
     * @param list<array{code: string, message: string, description: string}> $submissionErrors
     */
    private function __construct(
        public string $paymentRequestId,
        public string $tenantId,
        public string $externalReference,
        public string $invoiceNumber,
        public string $invoiceDate,
        public int $totalInclTaxCents,
        public string $state,
        public array $submissionErrors,
        public UrssafTrackingView $urssaf,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromPaymentRequest(PaymentRequest $paymentRequest): self
    {
        return new self(
            $paymentRequest->id()->value(),
            $paymentRequest->tenantId()->value(),
            $paymentRequest->externalReference()->value(),
            $paymentRequest->invoiceNumber()->value(),
            $paymentRequest->invoiceDate()->toString(),
            $paymentRequest->totalInclTax()->cents(),
            $paymentRequest->state()->value,
            array_map(
                static fn(SubmissionError $error): array => [
                    'code' => $error->code(),
                    'message' => $error->message(),
                    'description' => $error->description(),
                ],
                $paymentRequest->submissionErrors(),
            ),
            UrssafTrackingView::fromPaymentRequest($paymentRequest),
            $paymentRequest->timeline()->createdAt()->toIso8601(),
            $paymentRequest->timeline()->updatedAt()->toIso8601(),
        );
    }
}
