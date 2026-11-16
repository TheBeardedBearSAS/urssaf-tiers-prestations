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
 * Outcome of the reading of a draft: every violation found and, when possible, the request it describes.
 *
 * @internal
 */
interface DraftReading
{
    public function result(): ValidationResult;

    /**
     * @throws ValidationFailed when the draft is not valid
     */
    public function toPaymentRequest(LocalPaymentRequestId $id, PaymentRequestContext $context): PaymentRequest;
}
