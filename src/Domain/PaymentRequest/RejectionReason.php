<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

/**
 * Reason of a regularisation, contestation or cancellation (infoRejet.code, api-reference §3.5).
 * Backed by the official code.
 */
enum RejectionReason: string
{
    case RegularisationWrongAmount = 'REGUL_MNT_FACT';
    case RegularisationWrongQuantity = 'REGUL_NB_QTE_FACT';
    case RegularisationWrongServices = 'REGUL_PRESTA_FACT';
    case RegularisationOther = 'REGUL_AUTRE';
    case ContestationInvoiceError = 'CONTEST_ERR_FACT';
    case ContestationAlreadyPaid = 'CONTEST_FACT_REGLEE';
    case ContestationOther = 'CONTEST_AUTRE';
    case CancellationDuplicate = 'ANNUL_DBL';
    case CancellationWrongAmount = 'ANNUL_ERR_MNT';
    case CancellationTechnicalError = 'ANNUL_ERR_TECH';
    case CancellationOther = 'ANNUL_AUTRE';

    public function context(): RejectionContext
    {
        return match ($this) {
            self::RegularisationWrongAmount,
            self::RegularisationWrongQuantity,
            self::RegularisationWrongServices,
            self::RegularisationOther => RejectionContext::Regularisation,
            self::ContestationInvoiceError,
            self::ContestationAlreadyPaid,
            self::ContestationOther => RejectionContext::Contestation,
            self::CancellationDuplicate,
            self::CancellationWrongAmount,
            self::CancellationTechnicalError,
            self::CancellationOther => RejectionContext::Cancellation,
        };
    }
}
