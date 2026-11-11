<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

/**
 * One item of the method 070 response (infoDemandePaiements[]), as seen by URSSAF at query time.
 *
 * The status is kept raw ({@see StatusInfo}) so unknown codes are preserved; the PaymentRequest domain maps it.
 * The invoice number is the raw numFactureTiers echoed in demandePaiement (empty string when absent).
 */
final readonly class PaymentRequestSnapshot
{
    private function __construct(
        private PaymentRequestId $paymentRequestId,
        private string $invoiceNumber,
        private StatusInfo $status,
        private ?RejectionInfo $rejection,
        private ?TransferInfo $transfer,
    ) {}

    public static function of(
        PaymentRequestId $paymentRequestId,
        string $invoiceNumber,
        StatusInfo $status,
        ?RejectionInfo $rejection,
        ?TransferInfo $transfer,
    ): self {
        return new self($paymentRequestId, $invoiceNumber, $status, $rejection, $transfer);
    }

    public function paymentRequestId(): PaymentRequestId
    {
        return $this->paymentRequestId;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function status(): StatusInfo
    {
        return $this->status;
    }

    public function rejection(): ?RejectionInfo
    {
        return $this->rejection;
    }

    public function transfer(): ?TransferInfo
    {
        return $this->transfer;
    }
}
