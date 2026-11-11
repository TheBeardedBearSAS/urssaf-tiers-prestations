<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Query;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerRegistrationView;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RegistrationLocator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;

final readonly class GetCustomerRegistrationHandler
{
    public function __construct(private RegistrationLocator $registrations) {}

    /**
     * @throws RecordNotFound "customer_registration.not_found"
     */
    public function __invoke(GetCustomerRegistration $query): CustomerRegistrationView
    {
        return CustomerRegistrationView::fromRegistration($this->registrations->locate($query->tenantId, $query->customer));
    }
}
