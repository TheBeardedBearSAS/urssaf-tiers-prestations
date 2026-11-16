<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

/**
 * Every URSSAF "API Tiers de prestations" operation, implemented by the infrastructure layer.
 */
interface UrssafGateway extends CustomerRegistrar, TransmissionStatusReader, PaymentRequestSubmitter, PaymentRequestFinder {}
