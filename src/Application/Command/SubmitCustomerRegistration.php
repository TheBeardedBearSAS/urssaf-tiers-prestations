<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;

/**
 * Sends a pending customer registration to URSSAF (method 010). Meant to run asynchronously.
 */
final readonly class SubmitCustomerRegistration
{
    public function __construct(public RegistrationId $registrationId) {}
}
