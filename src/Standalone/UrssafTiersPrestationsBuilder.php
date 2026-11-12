<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Standalone;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\CredentialsProvider;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\EventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimiter;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TokenStore;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\TransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\IgnoredFailures;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\IgnoredOutbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\DoctrineTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrinePaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository\DoctrineWorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Event\NullEventPublisher;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\RandomUuidGenerator;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Id\SecureRandomBytes;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Logging\PsrLoggerFailureReporter;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryCustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryPaymentRequestRepository;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Persistence\InMemory\InMemoryWorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\TokenStore\InMemoryTokenStore;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Transaction\ImmediateTransactionManager;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\GatewayOptions;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\HttpTransport;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UrssafGatewayFactory;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\UtcSystemClock;

/**
 * Wires the package without a framework. Every "with" method returns a new builder.
 *
 * Defaults: in-memory repositories and token store (tests and prototypes: use withDoctrine() and a shared token store
 * in production), the UTC system clock, random UUIDs, no event publisher, no outbox, the default workflow policy.
 *
 * One property and one "with" method per collaborator, hence the counts.
 *
 * @mago-expect lint:too-many-properties
 * @mago-expect lint:too-many-methods
 */
final class UrssafTiersPrestationsBuilder
{
    private ?UrssafGateway $gateway = null;
    private TokenStore $tokenStore;
    private Clock $clock;
    private IdGenerator $ids;
    private EventPublisher $events;
    private ?LoggerInterface $logger = null;
    private ?RateLimiter $rateLimiter = null;
    private WorkflowPolicy $policy;
    private CustomerRegistrationRepository $registrations;
    private PaymentRequestRepository $paymentRequests;
    private TransactionManager $transactions;
    private ?WorkflowStatistics $statistics;
    private Outbox $outbox;
    private ?IntegrationEventSink $sink = null;

    private function __construct(
        private readonly HttpTransport $transport,
        private readonly CredentialsProvider $credentials,
        private readonly ApiConfiguration $configuration,
    ) {
        $this->tokenStore = new InMemoryTokenStore();
        $this->clock = new UtcSystemClock();
        $this->ids = new RandomUuidGenerator(new SecureRandomBytes());
        $this->events = new NullEventPublisher();
        $this->policy = new WorkflowPolicy();
        $registrations = new InMemoryCustomerRegistrationRepository();
        $paymentRequests = new InMemoryPaymentRequestRepository();
        $this->registrations = $registrations;
        $this->paymentRequests = $paymentRequests;
        $this->transactions = new ImmediateTransactionManager();
        $this->statistics = new InMemoryWorkflowStatistics($registrations, $paymentRequests);
        $this->outbox = new IgnoredOutbox();
    }

    public static function create(HttpTransport $transport, CredentialsProvider $credentials, ApiConfiguration $configuration): self
    {
        return new self($transport, $credentials, $configuration);
    }

    /**
     * A token store shared by every process that calls URSSAF (PSR-6 / PSR-16 store).
     */
    public function withTokenStore(TokenStore $tokenStore): self
    {
        return $this->with(static function (self $builder) use ($tokenStore): void {
            $builder->tokenStore = $tokenStore;
        });
    }

    public function withClock(Clock $clock): self
    {
        return $this->with(static function (self $builder) use ($clock): void {
            $builder->clock = $clock;
        });
    }

    public function withIdGenerator(IdGenerator $ids): self
    {
        return $this->with(static function (self $builder) use ($ids): void {
            $builder->ids = $ids;
        });
    }

    /**
     * Where the domain events are published after each commit (for instance a Psr14EventPublisher).
     */
    public function withEventPublisher(EventPublisher $events): self
    {
        return $this->with(static function (self $builder) use ($events): void {
            $builder->events = $events;
        });
    }

    /**
     * The logger of the HTTP gateway and of the event publication failures.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return $this->with(static function (self $builder) use ($logger): void {
            $builder->logger = $logger;
        });
    }

    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        return $this->with(static function (self $builder) use ($rateLimiter): void {
            $builder->rateLimiter = $rateLimiter;
        });
    }

    public function withPolicy(WorkflowPolicy $policy): self
    {
        return $this->with(static function (self $builder) use ($policy): void {
            $builder->policy = $policy;
        });
    }

    /**
     * Your own persistence; without statistics, getWorkflowOverview() is not available.
     */
    public function withRepositories(
        CustomerRegistrationRepository $registrations,
        PaymentRequestRepository $paymentRequests,
        TransactionManager $transactions,
        ?WorkflowStatistics $statistics,
    ): self {
        return $this->with(static function (self $builder) use ($registrations, $paymentRequests, $transactions, $statistics): void {
            $builder->registrations = $registrations;
            $builder->paymentRequests = $paymentRequests;
            $builder->transactions = $transactions;
            $builder->statistics = $statistics;
        });
    }

    /**
     * The Doctrine persistence of the package, on a dedicated entity manager (docs/doctrine.md).
     */
    public function withDoctrine(EntityManagerInterface $entityManager): self
    {
        return $this->withRepositories(
            new DoctrineCustomerRegistrationRepository($entityManager),
            new DoctrinePaymentRequestRepository($entityManager),
            new DoctrineTransactionManager($entityManager),
            new DoctrineWorkflowStatistics($entityManager),
        );
    }

    /**
     * Writes the integration events to the outbox (in the transaction of the repositories) and relays them to the sink.
     */
    public function withOutbox(Outbox $outbox, IntegrationEventSink $sink): self
    {
        return $this->with(static function (self $builder) use ($outbox, $sink): void {
            $builder->outbox = $outbox;
            $builder->sink = $sink;
        });
    }

    /**
     * Replaces the HTTP gateway (for instance with the Testing\SimulatedUrssafGateway in tests).
     */
    public function withGateway(UrssafGateway $gateway): self
    {
        return $this->with(static function (self $builder) use ($gateway): void {
            $builder->gateway = $gateway;
        });
    }

    public function build(): UrssafTiersPrestations
    {
        return new UrssafTiersPrestations(
            $this->gateway(),
            $this->registrations,
            $this->paymentRequests,
            $this->unitOfWork(),
            $this->clock,
            $this->ids,
            $this->policy,
            $this->statistics,
            $this->outbox,
            $this->sink,
        );
    }

    /**
     * @param Closure(self): void $change applied to a copy of the builder
     */
    private function with(Closure $change): self
    {
        $builder = clone $this;
        $change($builder);

        return $builder;
    }

    private function gateway(): UrssafGateway
    {
        return $this->gateway ?? UrssafGatewayFactory::createWith($this->transport, $this->credentials, $this->tokenStore, $this->configuration, $this->gatewayOptions());
    }

    private function unitOfWork(): UnitOfWork
    {
        $failures = $this->logger instanceof LoggerInterface ? new PsrLoggerFailureReporter($this->logger) : new IgnoredFailures();

        return new UnitOfWork($this->transactions, $this->events, $failures, $this->outbox);
    }

    private function gatewayOptions(): GatewayOptions
    {
        $options = GatewayOptions::defaults()->withClock($this->clock)->withIdGenerator($this->ids);
        if ($this->rateLimiter instanceof RateLimiter) {
            $options = $options->withRateLimiter($this->rateLimiter);
        }

        return $this->logger instanceof LoggerInterface ? $options->withLogger($this->logger) : $options;
    }
}
