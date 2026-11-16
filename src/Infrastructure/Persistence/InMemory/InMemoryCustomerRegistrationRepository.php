<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory;

use DateInterval;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Process-local {@see CustomerRegistrationRepository} for tests and demos.
 *
 * Identity semantics: like a database, it stores a snapshot. {@see self::save()} copies the aggregate (without its
 * pending domain events, which are never persisted) and every read returns a new copy, so a change is only visible
 * once saved. Because the stored copy is private, {@see self::claimForSubmission()} can apply
 * {@see CustomerRegistration::startSubmission()} to it and drop the event it records (the port records no event)
 * without losing events pending on the caller's instance.
 *
 * Lists are ordered by last update, oldest first; registrations updated at the same instant keep their insertion order.
 *
 * Implements the repository port: one method per query of the port, plus the uniqueness check.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class InMemoryCustomerRegistrationRepository implements CustomerRegistrationRepository
{
    /** @var array<string, CustomerRegistration> stored snapshots by registration id, in insertion order */
    private array $registrations = [];

    /**
     * Copies of every stored record (monitoring, tests).
     *
     * @return list<CustomerRegistration>
     */
    public function all(): array
    {
        return $this->selection()->copies();
    }

    /**
     * @throws InvariantViolation "customer_registration.duplicate_external_reference" or
     *                            "customer_registration.duplicate_urssaf_customer_id"
     */
    public function save(CustomerRegistration $registration): void
    {
        $this->assertUnique($registration);
        $snapshot = clone $registration;
        $snapshot->releaseEvents();
        $this->registrations[$registration->id()->value()] = $snapshot;
    }

    public function get(RegistrationId $id): CustomerRegistration
    {
        $stored = $this->registrations[$id->value()] ?? throw RecordNotFound::withIdentifier(
            'customer_registration',
            $id->value(),
        );

        return clone $stored;
    }

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?CustomerRegistration
    {
        return $this->selection()
            ->where(
                static fn(CustomerRegistration $registration): bool => $registration->tenantId()->equals($tenantId)
                    && $registration->externalReference()->equals($externalReference),
            )
            ->copies()[0] ?? null;
    }

    public function findByUrssafCustomerId(TenantId $tenantId, UrssafCustomerId $urssafCustomerId): ?CustomerRegistration
    {
        return $this->selection()
            ->where(
                static fn(CustomerRegistration $registration): bool => $registration->tenantId()->equals($tenantId)
                    && $registration->status()->urssafCustomerId()?->equals($urssafCustomerId) === true,
            )
            ->copies()[0] ?? null;
    }

    public function findPairingToFollow(TenantId $tenantId, int $limit): array
    {
        return $this->selection()
            ->where(
                static fn(CustomerRegistration $registration): bool => $registration->tenantId()->equals($tenantId)
                    && $registration->status()->state()->hasPairingToFollow(),
            )
            ->limit($limit)
            ->copies();
    }

    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return Selection::of($this->registrations)
            ->where(
                static fn(CustomerRegistration $registration): bool => $registration->status()->state() === RegistrationState::Submitting
                    && $registration->status()->isLastAttemptStartedBefore($olderThan, new DateInterval('PT0S')),
            )
            ->sortedBy(
                static fn(CustomerRegistration $a, CustomerRegistration $b): int => Selection::compareInstants(
                    $a->status()->lastAttemptAt(),
                    $b->status()->lastAttemptAt(),
                ),
            )
            ->limit($limit)
            ->copies();
    }

    public function findByState(TenantId $tenantId, RegistrationState $state, int $limit): array
    {
        return $this->selection()
            ->where(
                static fn(CustomerRegistration $registration): bool => $registration->tenantId()->equals($tenantId)
                    && $registration->status()->state() === $state,
            )
            ->limit($limit)
            ->copies();
    }

    public function tenantsWithPairingToFollow(): array
    {
        return array_map(
            static fn(CustomerRegistration $registration): TenantId => $registration->tenantId(),
            $this->selection()
                ->where(static fn(CustomerRegistration $registration): bool => $registration->status()->state()->hasPairingToFollow())
                ->distinctBy(static fn(CustomerRegistration $registration): string => $registration->tenantId()->value())
                ->all(),
        );
    }

    public function delete(RegistrationId $id): void
    {
        unset($this->registrations[$id->value()]);
    }

    public function claimForSubmission(RegistrationId $id, Instant $now): bool
    {
        $stored = $this->registrations[$id->value()] ?? null;
        if ($stored === null || $stored->status()->state() !== RegistrationState::Pending) {
            return false;
        }

        $stored->startSubmission($now);
        $stored->releaseEvents();

        return true;
    }

    /**
     * Stored registrations by last update (oldest first, insertion order on ties).
     *
     * @return Selection<CustomerRegistration>
     */
    private function selection(): Selection
    {
        return Selection::of($this->registrations)->sortedBy(
            static fn(CustomerRegistration $a, CustomerRegistration $b): int => Selection::compareInstants(
                $a->status()->updatedAt(),
                $b->status()->updatedAt(),
            ),
        );
    }

    private function assertUnique(CustomerRegistration $registration): void
    {
        $urssafId = $registration->status()->urssafCustomerId();
        foreach ($this->registrations as $stored) {
            if ($stored->id()->equals($registration->id()) || !$stored->tenantId()->equals($registration->tenantId())) {
                continue;
            }
            if ($stored->externalReference()->equals($registration->externalReference())) {
                throw InvariantViolation::because(
                    'customer_registration.duplicate_external_reference',
                    'A customer registration already exists for this external reference.',
                    ['external_reference' => $registration->externalReference()->value()],
                );
            }
            if ($urssafId instanceof UrssafCustomerId && $stored->status()->urssafCustomerId()?->equals($urssafId) === true) {
                throw InvariantViolation::because(
                    'customer_registration.duplicate_urssaf_customer_id',
                    'A customer registration already exists for this URSSAF customer id.',
                    ['urssaf_customer_id' => $urssafId->value()],
                );
            }
        }
    }
}
