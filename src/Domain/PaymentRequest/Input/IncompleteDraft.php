<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestContext;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;

/**
 * A draft of which a required part could not be parsed (its result therefore contains errors).
 *
 * @internal
 */
final readonly class IncompleteDraft implements DraftReading
{
    public function __construct(private ValidationResult $result) {}

    public function result(): ValidationResult
    {
        return $this->result;
    }

    public function toPaymentRequest(LocalPaymentRequestId $id, PaymentRequestContext $context): PaymentRequest
    {
        throw ValidationFailed::withResult($this->result);
    }
}
