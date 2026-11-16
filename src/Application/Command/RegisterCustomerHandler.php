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

use TheBeardedBear\UrssafTiersPrestations\Application\Exception\CustomerAlreadyRegistered;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Validates the customer and stores a pending registration; URSSAF is not called (see SubmitCustomerRegistration).
 *
 * Idempotent: registering again the same external reference with the same customer details returns the existing
 * registration id (whatever its state); different details are refused with {@see CustomerAlreadyRegistered}.
 */
final readonly class RegisterCustomerHandler
{
    public function __construct(
        private CustomerRegistrationRepository $registrations,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private CustomerFactory $customerFactory = new CustomerFactory(),
    ) {}

    /**
     * @throws ValidationFailed          with every violation of the customer draft
     * @throws InvariantViolation        "birth_date.in_future"
     * @throws CustomerAlreadyRegistered
     */
    public function __invoke(RegisterCustomer $command): RegistrationId
    {
        $customer = $this->customerFactory->fromDraft($command->customer);
        $existing = $this->registrations->findByExternalReference($command->tenantId, $command->externalReference);
        if ($existing instanceof CustomerRegistration) {
            if (!$existing->customer()->equals($customer)) {
                throw CustomerAlreadyRegistered::withExternalReference($command->externalReference, $existing->id()->value());
            }

            return $existing->id();
        }
        $registration = CustomerRegistration::request(
            RegistrationId::fromString($this->ids->generate()),
            $command->tenantId,
            $command->externalReference,
            $customer,
            Instant::fromDateTime($this->clock->now()),
        );

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($registration): RegistrationId {
            $this->registrations->save($registration);
            $events->record(...$registration->releaseEvents());

            return $registration->id();
        });
    }
}
