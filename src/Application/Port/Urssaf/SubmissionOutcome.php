<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

/**
 * Result of method 050 for ONE submitted payment request.
 *
 * Accepted  <=> idDemandePaiement is not empty (the request is integrated); statut is then a status code (e.g. "10")
 *           and errors returned alongside are kept as warnings.
 * Rejected  otherwise; statut is either a status code or an error code (e.g. "ERR_CMPT_BLOQUE"), kept raw, and the
 *           gateway classifies the errors ({@see RejectionKind}).
 * The invoice number is the raw numFactureTiers echoed by URSSAF (empty when not echoed): outcomes are matched to the
 * submitted requests by invoice number ({@see self::concerns()}).
 */
final readonly class SubmissionOutcome
{
    private function __construct(
        private string $invoiceNumber,
        private string $rawStatus,
        private ?PaymentRequestId $paymentRequestId,
        private ApiErrors $errors,
        private ?RejectionKind $rejectionKind,
    ) {}

    public static function accepted(string $invoiceNumber, PaymentRequestId $paymentRequestId, string $statusCode): self
    {
        return self::acceptedWithWarnings($invoiceNumber, $paymentRequestId, $statusCode, ApiErrors::none());
    }

    /**
     * Integrated although URSSAF also returned errors: they are kept as warnings ({@see self::errors()}).
     */
    public static function acceptedWithWarnings(
        string $invoiceNumber,
        PaymentRequestId $paymentRequestId,
        string $statusCode,
        ApiErrors $warnings,
    ): self {
        return new self($invoiceNumber, $statusCode, $paymentRequestId, $warnings, null);
    }

    /**
     * @throws InvariantViolation "submission_outcome.missing_errors" when no error explains the rejection
     */
    public static function rejected(string $invoiceNumber, string $rawStatus, ApiErrors $errors, RejectionKind $kind): self
    {
        if ($errors->isEmpty()) {
            throw InvariantViolation::because(
                'submission_outcome.missing_errors',
                'A rejected submission outcome must carry at least one error.',
            );
        }

        return new self($invoiceNumber, $rawStatus, null, $errors, $kind);
    }

    public function isAccepted(): bool
    {
        return $this->paymentRequestId instanceof PaymentRequestId;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    /**
     * True when URSSAF echoed this invoice number.
     */
    public function concerns(InvoiceNumber $invoiceNumber): bool
    {
        return $this->invoiceNumber === $invoiceNumber->value();
    }

    public function rawStatus(): string
    {
        return $this->rawStatus;
    }

    /**
     * Null when rejected.
     */
    public function paymentRequestId(): ?PaymentRequestId
    {
        return $this->paymentRequestId;
    }

    /**
     * The errors of a rejection, or the warnings returned with an accepted request (usually none).
     */
    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    /**
     * Null when accepted.
     */
    public function rejectionKind(): ?RejectionKind
    {
        return $this->rejectionKind;
    }
}
