<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Specification;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Invoice;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Validator;

/**
 * Catalogue of the payment request rules, grouped by what they need:
 * - {@see self::line()}: rules of a single line;
 * - {@see self::invoice()}: cross-line / invoice rules (lines excluded);
 * - {@see self::intrinsic()}: line + invoice rules, i.e. everything that does not depend on time (enforced by
 *   {@see \TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest::create()});
 * - {@see self::contextual()}: rules depending on "now" and on the pairing acceptance instant.
 */
final class InvoiceRules
{
    /**
     * @return Validator<ServiceLine>
     */
    public static function line(): Validator
    {
        return AllOf::of(
            new LineVatIsConsistent(),
            new LineTotalMatchesUnitPrice(),
            new ActivityBelongsToNature(),
            new NatureIsEligibleForTaxCredit(),
        );
    }

    /**
     * @return Validator<Invoice>
     */
    public static function invoice(): Validator
    {
        return AllOf::of(
            new AtLeastOneLine(),
            new TotalsAreConsistent(),
            new DepositWithinLimit(),
            new LinesPeriodsWithinInvoicePeriod(),
        );
    }

    /**
     * @return Validator<Invoice>
     */
    public static function intrinsic(): Validator
    {
        return AllOf::of(new EveryLine(self::line()), self::invoice());
    }

    /**
     * @return Validator<Invoice>
     */
    public static function contextual(Instant $now, Instant $pairingAcceptedAt): Validator
    {
        return AllOf::of(new EmploymentPeriodNotInFuture($now), new EmploymentPeriodNotBeforePairing($pairingAcceptedAt));
    }
}
