<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

/**
 * Loads the customer registration designated by a {@see CustomerSelector}, within one tenant.
 */
final readonly class RegistrationLocator
{
    private const RECORD_TYPE = 'customer_registration';

    public function __construct(private CustomerRegistrationRepository $registrations) {}

    /**
     * @throws RecordNotFound "customer_registration.not_found" (also for a registration of another tenant)
     */
    public function locate(TenantId $tenantId, CustomerSelector $selector): CustomerRegistration
    {
        $reference = $selector->reference;
        if ($reference instanceof ExternalReference) {
            return $this->registrations->findByExternalReference($tenantId, $reference)
                ?? throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }
        $registration = $this->registrations->get($reference);
        if (!$registration->tenantId()->equals($tenantId)) {
            throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }

        return $registration;
    }
}
