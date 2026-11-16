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
 * Raw payment request as provided by the host, turned into a {@see \TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest}
 * by {@see \TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestFactory}.
 *
 * - externalReference: host record reference (idempotency / correlation);
 * - invoiceNumber: at most 50 characters; invoiceDate: "Y-m-d";
 * - employmentPeriod: from the start of the first service to the end of the last one;
 * - totals: decimal strings with at most 2 decimals; deposit: optional.
 *
 * Customer, billing third party and time references come from the
 * {@see \TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestContext}.
 */
final readonly class PaymentRequestDraft
{
    /**
     * @param list<ServiceLineDraft> $lines
     */
    public function __construct(
        public ?string $externalReference,
        public ?string $invoiceNumber,
        public ?string $invoiceDate,
        public ?PeriodDraft $employmentPeriod,
        public ?string $totalInclTax,
        public ?string $totalExclTax,
        public array $lines,
        public ?DepositDraft $deposit,
    ) {}
}
