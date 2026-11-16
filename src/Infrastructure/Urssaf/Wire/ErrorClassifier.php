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
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RefusalReason;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * Translates the URSSAF error codes into the port concepts, so that the application never compares wire codes.
 */
final class ErrorClassifier
{
    /**
     * Kind of a method 050 per-item rejection, by precedence: duplicate invoice number (the request may be live at
     * URSSAF), customer account blocked, mandate expired, customer / provider link missing, only technical errors
     * (retryable); anything else is final.
     */
    public static function rejectionKind(ApiErrors $errors): RejectionKind
    {
        $codes = array_map(ApiErrorCode::fromCode(...), $errors->codes());

        return match (true) {
            \in_array(ApiErrorCode::DuplicateInvoice, $codes, true) => RejectionKind::Duplicate,
            \in_array(ApiErrorCode::CustomerAccountBlocked, $codes, true) => RejectionKind::CustomerBlocked,
            \in_array(ApiErrorCode::MandateExpired, $codes, true) => RejectionKind::MandateExpired,
            \in_array(ApiErrorCode::NoCustomerProviderLink, $codes, true) => RejectionKind::PairingMissing,
            $codes !== [] && !\in_array(false, array_map(self::isTechnical(...), $codes), true) => RejectionKind::Retryable,
            default => RejectionKind::Final,
        };
    }

    /**
     * Meaning of a refused call (HTTP 400).
     */
    public static function refusalReason(ApiErrors $errors): RefusalReason
    {
        return match (true) {
            $errors->hasCode(ApiErrorCode::NoActiveRegistration->value) => RefusalReason::NoActiveRegistration,
            $errors->hasCode(ApiErrorCode::ProviderCustomerLinkAlreadyExists->value) => RefusalReason::AlreadyRegistered,
            default => RefusalReason::Generic,
        };
    }

    private static function isTechnical(?ApiErrorCode $code): bool
    {
        return $code?->isTechnical() === true;
    }
}
