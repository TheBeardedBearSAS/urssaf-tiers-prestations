<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

/**
 * Every URSSAF "API Tiers de prestations" operation, implemented by the infrastructure layer.
 */
interface UrssafGateway extends CustomerRegistrar, TransmissionStatusReader, PaymentRequestSubmitter, PaymentRequestFinder {}
