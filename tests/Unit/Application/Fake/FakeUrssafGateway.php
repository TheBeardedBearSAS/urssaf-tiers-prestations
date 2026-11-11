<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake;

use Closure;
use LogicException;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use Throwable;

/**
 * Scripted gateway: each method answers with the next scripted response (a value, or a throwable to throw), or with
 * the result of the scripted closure. Calls are journaled and recorded.
 */
final class FakeUrssafGateway implements UrssafGateway
{
    /** @var list<UrssafCustomerId|Throwable> */
    private array $registerResponses = [];

    /** @var array<string, CustomerTransmissionStatus|Throwable> */
    private array $statuses = [];

    /** @var null|Closure(list<PaymentRequest>): (list<SubmissionOutcome>|Throwable) */
    private ?Closure $submitter = null;

    /** @var null|Closure(PaymentRequestSearch): (list<PaymentRequestSnapshot>|Throwable) */
    private ?Closure $finder = null;

    /** @var list<list<string>> invoice numbers of each submit() call */
    private array $submissions = [];

    /** @var list<PaymentRequestSearch> */
    private array $searches = [];

    /** @var list<string> */
    private array $tenants = [];

    public function __construct(private readonly Journal $journal = new Journal()) {}

    public function willRegister(UrssafCustomerId|Throwable $response): void
    {
        $this->registerResponses[] = $response;
    }

    public function willReportStatus(UrssafCustomerId $customerId, CustomerTransmissionStatus|Throwable $response): void
    {
        $this->statuses[$customerId->value()] = $response;
    }

    /**
     * @param Closure(list<PaymentRequest>): (list<SubmissionOutcome>|Throwable) $submitter
     */
    public function willSubmitWith(Closure $submitter): void
    {
        $this->submitter = $submitter;
    }

    /**
     * @param Closure(PaymentRequestSearch): (list<PaymentRequestSnapshot>|Throwable) $finder
     */
    public function willFindWith(Closure $finder): void
    {
        $this->finder = $finder;
    }

    public function register(TenantId $tenantId, CustomerRegistration $registration): UrssafCustomerId
    {
        $this->journal->add('register:' . $registration->id()->value());
        $this->tenants[] = $tenantId->value();
        $response = array_shift($this->registerResponses) ?? throw new LogicException('Unexpected register() call.');

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function transmissionStatus(TenantId $tenantId, UrssafCustomerId $customerId): CustomerTransmissionStatus
    {
        $this->journal->add('status:' . $customerId->value());
        $this->tenants[] = $tenantId->value();
        $response = $this->statuses[$customerId->value()] ?? throw new LogicException('Unexpected transmissionStatus() call.');

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function submit(TenantId $tenantId, PaymentRequest ...$requests): array
    {
        $requests = array_values($requests);
        $this->journal->add('submit:' . \count($requests));
        $this->tenants[] = $tenantId->value();
        $this->submissions[] = array_map(static fn(PaymentRequest $r): string => $r->invoiceNumber()->value(), $requests);
        $submitter = $this->submitter ?? throw new LogicException('Unexpected submit() call.');
        $response = $submitter($requests);

        return $response instanceof Throwable ? throw $response : $response;
    }

    public function search(TenantId $tenantId, PaymentRequestSearch $search): array
    {
        $this->journal->add('search');
        $this->tenants[] = $tenantId->value();
        $this->searches[] = $search;
        $finder = $this->finder ?? throw new LogicException('Unexpected search() call.');
        $response = $finder($search);

        return $response instanceof Throwable ? throw $response : $response;
    }

    /**
     * @return list<list<string>>
     */
    public function submissions(): array
    {
        return $this->submissions;
    }

    /**
     * @return list<PaymentRequestSearch>
     */
    public function searches(): array
    {
        return $this->searches;
    }

    /**
     * @return list<string> tenant of each call
     */
    public function tenants(): array
    {
        return $this->tenants;
    }
}
