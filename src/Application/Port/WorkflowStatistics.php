<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port;

/**
 * Counts the records by tenant and state, for monitoring. A port of its own (not a repository method), so that a custom
 * persistence can do without it.
 */
interface WorkflowStatistics
{
    /**
     * @return list<StateCount> the customer registrations by tenant and state (pairs without record are omitted)
     */
    public function registrationStates(): array;

    /**
     * @return list<StateCount> the payment requests by tenant and submission state (pairs without record are omitted)
     */
    public function paymentRequestStates(): array;
}
