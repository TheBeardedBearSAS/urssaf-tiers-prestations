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

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineAmounts;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineComplement;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification\InvoiceRules;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Unit;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\IntervenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;

/**
 * Parses a service line draft and reports the rules of the parsed line ({@see InvoiceRules::line()}).
 * Returns null when a required part could not be built.
 *
 * @internal
 */
final readonly class ServiceLineReader
{
    public function __construct(private DraftReader $reader, private PeriodReader $periods) {}

    public function read(string $path, ServiceLineDraft $draft): ?ServiceLine
    {
        $nature = $this->reader->required($path . '.natureCode', $draft->natureCode, NatureCode::fromString(...));
        $activity = $this->reader->optional($path . '.activityCode', $draft->activityCode, ActivityCode::fromString(...));
        $unit = $this->reader->required($path . '.unit', $draft->unit, Unit::fromString(...));
        $quantity = $this->reader->required($path . '.quantity', $draft->quantity, Quantity::fromDecimalString(...));
        $unitPrice = $this->reader->required($path . '.unitPrice', $draft->unitPrice, UnitPrice::fromDecimalString(...));
        $amounts = $this->amounts($path . '.amounts', $draft->amounts);
        $intervenant = $this->reader->required($path . '.intervenantId', $draft->intervenantId, IntervenantId::fromString(...));
        $period = $this->periods->optional($path . '.period', $draft->period);
        $complement = $this->reader->optional($path . '.complement', $draft->complement, LineComplement::fromString(...));
        if ($nature === null || $unit === null || $quantity === null || $unitPrice === null) {
            return null;
        }
        if (!$amounts instanceof LineAmounts || $intervenant === null) {
            return null;
        }
        $line = new ServiceLine($nature, $activity, $unit, $quantity, $unitPrice, $amounts, $intervenant, $period, $complement);
        $this->reader->merge(InvoiceRules::line()->validate($line)->withPathPrefix($path));

        return $line;
    }

    private function amounts(string $path, ?LineAmountsDraft $draft): ?LineAmounts
    {
        if (!$draft instanceof LineAmountsDraft) {
            $this->reader->missing($path);

            return null;
        }
        $inclTax = $this->reader->required($path . '.totalInclTax', $draft->totalInclTax, Money::fromDecimalString(...));
        $exclTax = $this->reader->required($path . '.totalExclTax', $draft->totalExclTax, Money::fromDecimalString(...));
        $vat = $this->reader->required($path . '.vatAmount', $draft->vatAmount, Money::fromDecimalString(...));

        return $inclTax === null || $exclTax === null || $vat === null ? null : LineAmounts::of($inclTax, $exclTax, $vat);
    }
}
