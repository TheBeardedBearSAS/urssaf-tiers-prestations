<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Records a customer registration request (validated locally, submitted later by SubmitCustomerRegistration).
 */
final readonly class RegisterCustomer
{
    public function __construct(
        public TenantId $tenantId,
        public ExternalReference $externalReference,
        public CustomerDraft $customer,
    ) {}
}
