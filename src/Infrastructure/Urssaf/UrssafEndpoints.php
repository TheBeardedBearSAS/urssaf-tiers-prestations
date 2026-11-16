<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf;

/**
 * Paths of the URSSAF API (relative to {@see ApiConfiguration::baseUri()}).
 */
final class UrssafEndpoints
{
    public const TOKEN_PATH = '/api/oauth/v1/token';
    public const TOKEN_SCOPE = 'homeplus.tiersprestations';
    public const API_BASE_PATH = '/atp/v1/tiersPrestations';
    public const CUSTOMER_PATH = self::API_BASE_PATH . '/particulier';
    public const PAYMENT_REQUEST_PATH = self::API_BASE_PATH . '/demandePaiement';
    public const PAYMENT_REQUEST_SEARCH_PATH = self::PAYMENT_REQUEST_PATH . '/rechercher';
}
