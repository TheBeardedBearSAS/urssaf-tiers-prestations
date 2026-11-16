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

/**
 * Raw deposit: amount as a decimal string ("2.75", at most 2 decimals) and payment date ("Y-m-d").
 */
final readonly class DepositDraft
{
    public function __construct(public ?string $amount, public ?string $paymentDate) {}
}
