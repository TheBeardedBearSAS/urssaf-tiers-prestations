<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Stores copies (as a database would): changes are only visible after save(). Insertion order.
 *
 * Implements the whole repository port plus test helpers, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final class InMemoryCustomerRegistrations implements CustomerRegistrationRepository
{
    /** @var array<string, CustomerRegistration> */
    private array $stored = [];

    /** @var list<string> */
    private array $refusedClaims = [];

    public function __construct(private readonly Journal $journal = new Journal()) {}

    /**
     * Stores without journaling (test setup).
     */
    public function add(CustomerRegistration ...$registrations): void
    {
        foreach ($registrations as $registration) {
            $this->store($registration);
        }
    }

    /**
     * Simulates another worker winning the claim.
     */
    public function refuseClaim(RegistrationId $id): void
    {
        $this->refusedClaims[] = $id->value();
    }

    public function save(CustomerRegistration $registration): void
    {
        $this->journal->add('save:' . $registration->id()->value());
        $this->store($registration);
    }

    public function get(RegistrationId $id): CustomerRegistration
    {
        return clone ($this->stored[$id->value()] ?? throw RecordNotFound::withIdentifier('customer_registration', $id->value()));
    }

    public function findByExternalReference(TenantId $tenantId, ExternalReference $externalReference): ?CustomerRegistration
    {
        return $this->filter(static fn(CustomerRegistration $r): bool => $r->tenantId()->equals($tenantId)
            && $r->externalReference()->equals($externalReference))[0] ?? null;
    }

    public function findByUrssafCustomerId(TenantId $tenantId, UrssafCustomerId $urssafCustomerId): ?CustomerRegistration
    {
        return $this->filter(static fn(CustomerRegistration $r): bool => $r->tenantId()->equals($tenantId)
            && $r->status()->urssafCustomerId()?->equals($urssafCustomerId) === true)[0] ?? null;
    }

    public function findPairingToFollow(TenantId $tenantId, int $limit): array
    {
        return \array_slice($this->filter(static fn(CustomerRegistration $r): bool => $r->tenantId()->equals($tenantId)
            && $r->status()->state()->hasPairingToFollow()), 0, $limit);
    }

    public function findStaleSubmissions(Instant $olderThan, int $limit): array
    {
        return \array_slice($this->filter(static fn(CustomerRegistration $r): bool => $r->status()->state() === RegistrationState::Submitting
            && $r->status()->lastAttemptAt()?->isAfter($olderThan) === false), 0, $limit);
    }

    public function findByState(TenantId $tenantId, RegistrationState $state, int $limit): array
    {
        return \array_slice($this->filter(static fn(CustomerRegistration $r): bool => $r->tenantId()->equals($tenantId)
            && $r->status()->state() === $state), 0, $limit);
    }

    public function delete(RegistrationId $id): void
    {
        $this->journal->add('delete:' . $id->value());
        unset($this->stored[$id->value()]);
    }

    public function tenantsWithPairingToFollow(): array
    {
        $tenants = [];
        foreach ($this->stored as $registration) {
            if (!$registration->status()->state()->hasPairingToFollow()) {
                continue;
            }
            $tenants[$registration->tenantId()->value()] = $registration->tenantId();
        }

        return array_values($tenants);
    }

    public function claimForSubmission(RegistrationId $id, Instant $now): bool
    {
        $stored = $this->stored[$id->value()] ?? null;
        if ($stored === null || \in_array($id->value(), $this->refusedClaims, true) || $stored->status()->state() !== RegistrationState::Pending) {
            return false;
        }
        $this->journal->add('claim:' . $id->value());
        $stored->startSubmission($now);
        $stored->releaseEvents();

        return true;
    }

    /**
     * Stored state, without copy (assertions).
     */
    public function stored(string $id): CustomerRegistration
    {
        return $this->stored[$id] ?? throw RecordNotFound::withIdentifier('customer_registration', $id);
    }

    private function store(CustomerRegistration $registration): void
    {
        $copy = clone $registration;
        $copy->releaseEvents();
        $this->stored[$registration->id()->value()] = $copy;
    }

    /**
     * @param callable(CustomerRegistration): bool $predicate
     *
     * @return list<CustomerRegistration>
     */
    private function filter(callable $predicate): array
    {
        return array_values(array_map(
            static fn(CustomerRegistration $r): CustomerRegistration => clone $r,
            array_filter($this->stored, $predicate),
        ));
    }
}
