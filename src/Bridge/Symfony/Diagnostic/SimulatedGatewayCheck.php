<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Diagnostic;

use Override;

/**
 * Registered instead of the authentication check when "gateway.simulated" is enabled.
 */
final readonly class SimulatedGatewayCheck implements InstallationCheck
{
    #[Override]
    public function run(CheckContext $context): array
    {
        return [new CheckResult('gateway', '', CheckStatus::Warning, 'Simulated gateway ("gateway.simulated"): nothing is sent to URSSAF.')];
    }
}
