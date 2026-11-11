<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\CompleteDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\DraftReader;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\DraftReading;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\IncompleteDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\InvoiceReader;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\InvoiceRules;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;

/**
 * Turns a raw {@see PaymentRequestDraft} into a pending {@see PaymentRequest}, reporting every violation at once:
 * - each field is parsed into its value object ("field.required" when missing, value object codes otherwise), with
 *   paths such as "invoiceNumber", "employmentPeriod.start", "deposit.amount", "lines[0].unitPrice";
 * - the rules of each parsed line ({@see InvoiceRules::line()}), nested under "lines[i]";
 * - once every required field is valid: the invoice rules ({@see InvoiceRules::invoice()}) and the time-dependent
 *   rules ({@see InvoiceRules::contextual()}). Cross-field rules are not evaluated on an incomplete invoice.
 *
 * Warnings (e.g. "line.not_eligible") never block the creation; get them through {@see self::validate()}.
 */
final readonly class PaymentRequestFactory
{
    public function validate(PaymentRequestDraft $draft, PaymentRequestContext $context): ValidationResult
    {
        return $this->read($draft, $context)->result();
    }

    /**
     * @throws ValidationFailed with every error (and warning) found
     */
    public function fromDraft(
        LocalPaymentRequestId $id,
        PaymentRequestDraft $draft,
        PaymentRequestContext $context,
    ): PaymentRequest {
        return $this->read($draft, $context)->toPaymentRequest($id, $context);
    }

    private function read(PaymentRequestDraft $draft, PaymentRequestContext $context): DraftReading
    {
        $reader = new DraftReader();
        $reference = $reader->required('externalReference', $draft->externalReference, ExternalReference::fromString(...));
        $invoice = (new InvoiceReader($reader, $context))->read($draft);
        if ($invoice instanceof Invoice) {
            $reader->merge(InvoiceRules::invoice()->validate($invoice));
            $reader->merge(InvoiceRules::contextual($context->now(), $context->pairingAcceptedAt())->validate($invoice));
        }

        if ($reference === null || !$invoice instanceof Invoice) {
            return new IncompleteDraft($reader->result());
        }

        return new CompleteDraft($reader->result(), $reference, $invoice);
    }
}
