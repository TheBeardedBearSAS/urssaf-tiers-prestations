<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message;

/**
 * Asks to send a pending customer registration to URSSAF (method 010).
 */
final readonly class SubmitCustomerRegistrationMessage
{
    public function __construct(public string $registrationId) {}
}
