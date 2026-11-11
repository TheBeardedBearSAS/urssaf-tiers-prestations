<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Exception;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;

/**
 * Thrown when a tenant registers again an external reference with different customer details.
 *
 * Violation code: "customer_registration.already_registered".
 */
final class CustomerAlreadyRegistered extends DomainException
{
    public static function withExternalReference(ExternalReference $externalReference, string $registrationId): self
    {
        return new self(
            \sprintf('The external reference "%s" is already registered with other customer details.', $externalReference->value()),
            'customer_registration.already_registered',
            ['external_reference' => $externalReference->value(), 'registration_id' => $registrationId],
        );
    }
}
