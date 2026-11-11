<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message;

/**
 * Asks to send a pending customer registration to URSSAF (method 010).
 */
final readonly class SubmitCustomerRegistrationMessage
{
    public function __construct(public string $registrationId) {}
}
