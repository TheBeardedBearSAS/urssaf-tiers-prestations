<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Exception;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;

/**
 * Thrown when a tenant creates a payment request whose invoice number is already used by another external reference
 * (URSSAF would answer ERR_FACTURE_DOUBLON).
 *
 * Violation code: "payment_request.duplicate_invoice_number".
 */
final class DuplicateInvoiceNumber extends DomainException
{
    public static function of(InvoiceNumber $invoiceNumber, string $existingPaymentRequestId): self
    {
        return new self(
            \sprintf('The invoice number "%s" is already used by another payment request.', $invoiceNumber->value()),
            'payment_request.duplicate_invoice_number',
            ['invoice_number' => $invoiceNumber->value(), 'payment_request_id' => $existingPaymentRequestId],
        );
    }
}
