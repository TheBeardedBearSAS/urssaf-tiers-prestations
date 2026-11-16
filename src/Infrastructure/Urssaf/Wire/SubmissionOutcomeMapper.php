<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the method 050 response (one item per submitted payment request, same order) to {@see SubmissionOutcome}s.
 *
 * Accepted <=> `idDemandePaiement` is not empty (the request is integrated); errors returned alongside are kept as
 * warnings. Otherwise the errors are classified ({@see ErrorClassifier::rejectionKind()}). `statut` is a code (string
 * or integer) that may be an error code, kept raw. The request-side mapping of method 050 is not part of this class.
 */
final readonly class SubmissionOutcomeMapper
{
    public function __construct(private ErrorPayloadParser $errorParser = new ErrorPayloadParser()) {}

    /**
     * @return list<SubmissionOutcome>
     *
     * @throws UnexpectedUrssafResponse
     */
    public function mapAll(Payload $response): array
    {
        return array_map($this->map(...), $response->entries());
    }

    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $item): SubmissionOutcome
    {
        $invoiceNumber = $item->string('numFactureTiers') ?? '';
        $status = $item->code('statut') ?? '';
        $paymentRequestId = $item->string('idDemandePaiement') ?? '';
        $errors = $this->errorParser->parseEntries($item->items('errors'));

        if ($paymentRequestId !== '') {
            return SubmissionOutcome::acceptedWithWarnings($invoiceNumber, $this->paymentRequestId($paymentRequestId), $status, $errors);
        }

        return $this->rejected($invoiceNumber, $status, $errors);
    }

    private function rejected(string $invoiceNumber, string $status, ApiErrors $errors): SubmissionOutcome
    {
        if ($errors->isEmpty()) {
            throw UnexpectedUrssafResponse::invalidPayload(
                \sprintf('invoice "%s" has neither a payment request id nor an error', $invoiceNumber),
            );
        }

        return SubmissionOutcome::rejected($invoiceNumber, $status, $errors, ErrorClassifier::rejectionKind($errors));
    }

    private function paymentRequestId(string $value): PaymentRequestId
    {
        try {
            return PaymentRequestId::fromString($value);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('payment request id is not valid', $exception);
        }
    }
}
