<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Dto;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;

/**
 * Read model of a customer registration (primitives only). Instants are ISO 8601 UTC strings.
 */
final readonly class CustomerRegistrationView
{
    /**
     * @param list<array{code: string, message: string}> $lastErrors
     */
    private function __construct(
        public string $registrationId,
        public string $tenantId,
        public string $externalReference,
        public string $birthName,
        public string $firstNames,
        public string $state,
        public bool $canReceivePaymentRequests,
        public ?string $urssafCustomerId,
        public ?string $pairingAcceptedAt,
        public array $lastErrors,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromRegistration(CustomerRegistration $registration): self
    {
        $status = $registration->status();
        $civilStatus = $registration->customer()->civilStatus();

        return new self(
            $registration->id()->value(),
            $registration->tenantId()->value(),
            $registration->externalReference()->value(),
            $civilStatus->birthName()->value(),
            $civilStatus->firstNames()->value(),
            $status->state()->value,
            $status->canReceivePaymentRequests(),
            $status->urssafCustomerId()?->value(),
            $status->pairingAcceptedAt()?->toIso8601(),
            array_map(
                static fn(RejectionCause $cause): array => ['code' => $cause->code(), 'message' => $cause->message()],
                $status->lastErrors(),
            ),
            $registration->createdAt()->toIso8601(),
            $status->updatedAt()->toIso8601(),
        );
    }
}
