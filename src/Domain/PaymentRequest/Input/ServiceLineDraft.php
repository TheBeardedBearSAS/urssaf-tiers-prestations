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
 * Raw service line as provided by the host. Every value is optional here so that missing values are reported as
 * violations ("field.required") instead of type errors.
 *
 * - natureCode: §3.1 code ("60"); activityCode: optional §3.2 code ("30A001"), blank = none;
 * - unit: "HEURE" or "FORFAIT"; quantity: decimal string, > 0, at most 3 decimals ("1.75");
 * - unitPrice: decimal string incl. tax, at most 3 decimals; amounts: decimal strings, at most 2 decimals;
 * - intervenantId: "SAP" + 9 digits or "SIR" + 14 digits; period: optional; complement: optional free text.
 */
final readonly class ServiceLineDraft
{
    public function __construct(
        public ?string $natureCode,
        public ?string $activityCode,
        public ?string $unit,
        public ?string $quantity,
        public ?string $unitPrice,
        public ?LineAmountsDraft $amounts,
        public ?string $intervenantId,
        public ?PeriodDraft $period,
        public ?string $complement,
    ) {}
}
