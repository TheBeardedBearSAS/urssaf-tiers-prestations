<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

/**
 * Ids of the private services of the bundle that have no interface to be identified by.
 */
final class ServiceIds
{
    public const SYMFONY_HTTP_CLIENT = 'urssaf_tiers_prestations.http_client';
    public const PSR18_CLIENT = 'urssaf_tiers_prestations.psr18_client';
    public const API_CONFIGURATION = 'urssaf_tiers_prestations.api_configuration';
    public const TOKEN_PROVIDER = 'urssaf_tiers_prestations.token_provider';
    /** Token provider of the "urssaf:check" command: its own in-memory store, so that every check asks URSSAF. */
    public const PROBE_TOKEN_PROVIDER = 'urssaf_tiers_prestations.probe_token_provider';
    public const JSON_CLIENT = 'urssaf_tiers_prestations.json_client';
}
