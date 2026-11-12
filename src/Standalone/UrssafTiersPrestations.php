<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Standalone;

use LogicException;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequestHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\PurgeOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleRegistrationsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleSubmissionsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RelayOutboxHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPendingPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllCustomerPairingsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairingHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\IntegrationEventSink;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Outbox;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafGateway;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\WorkflowStatistics;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetWorkflowOverviewHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\PaymentRequestBuilder;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RegistrationLocator;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestRepository;

/**
 * Every use case of the package wired together, for applications without the Symfony bundle; built by
 * {@see UrssafTiersPrestationsBuilder}. Each method returns a new handler (handlers are stateless).
 *
 * One accessor per use case, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final readonly class UrssafTiersPrestations
{
    public function __construct(
        private UrssafGateway $gateway,
        private CustomerRegistrationRepository $registrations,
        private PaymentRequestRepository $paymentRequests,
        private UnitOfWork $unitOfWork,
        private Clock $clock,
        private IdGenerator $ids,
        private WorkflowPolicy $policy,
        private ?WorkflowStatistics $statistics,
        private Outbox $outbox,
        private ?IntegrationEventSink $sink,
    ) {}

    public function gateway(): UrssafGateway
    {
        return $this->gateway;
    }

    public function registerCustomer(): RegisterCustomerHandler
    {
        return new RegisterCustomerHandler($this->registrations, $this->ids, $this->clock, $this->unitOfWork);
    }

    public function submitCustomerRegistration(): SubmitCustomerRegistrationHandler
    {
        return new SubmitCustomerRegistrationHandler($this->registrations, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function syncCustomerPairing(): SyncCustomerPairingHandler
    {
        return new SyncCustomerPairingHandler($this->registrations, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function syncAllCustomerPairings(): SyncAllCustomerPairingsHandler
    {
        return new SyncAllCustomerPairingsHandler($this->registrations, $this->syncCustomerPairing());
    }

    public function reconcileStaleRegistrations(): ReconcileStaleRegistrationsHandler
    {
        return new ReconcileStaleRegistrationsHandler($this->registrations, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function createPaymentRequest(): CreatePaymentRequestHandler
    {
        return new CreatePaymentRequestHandler(
            $this->paymentRequests,
            new RegistrationLocator($this->registrations),
            $this->ids,
            new PaymentRequestBuilder($this->clock, $this->policy),
            $this->unitOfWork,
        );
    }

    public function submitPaymentRequests(): SubmitPaymentRequestsHandler
    {
        return new SubmitPaymentRequestsHandler($this->paymentRequests, $this->gateway, $this->clock, $this->unitOfWork, $this->policy, $this->registrations);
    }

    public function submitPendingPaymentRequests(): SubmitPendingPaymentRequestsHandler
    {
        return new SubmitPendingPaymentRequestsHandler($this->paymentRequests, $this->submitPaymentRequests(), $this->policy);
    }

    public function syncPaymentRequestStatuses(): SyncPaymentRequestStatusesHandler
    {
        return new SyncPaymentRequestStatusesHandler($this->paymentRequests, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function syncAllPaymentRequestStatuses(): SyncAllPaymentRequestStatusesHandler
    {
        return new SyncAllPaymentRequestStatusesHandler($this->paymentRequests, $this->syncPaymentRequestStatuses(), $this->clock, $this->policy);
    }

    public function reconcileStaleSubmissions(): ReconcileStaleSubmissionsHandler
    {
        return new ReconcileStaleSubmissionsHandler($this->paymentRequests, $this->gateway, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function requeueAfterInvalidCredentials(): RequeueAfterInvalidCredentialsHandler
    {
        return new RequeueAfterInvalidCredentialsHandler($this->registrations, $this->paymentRequests, $this->clock, $this->unitOfWork, $this->policy);
    }

    public function getCustomerRegistration(): GetCustomerRegistrationHandler
    {
        return new GetCustomerRegistrationHandler(new RegistrationLocator($this->registrations));
    }

    public function findPaymentRequests(): FindPaymentRequestsHandler
    {
        return new FindPaymentRequestsHandler($this->paymentRequests);
    }

    /**
     * @throws LogicException when custom repositories were given without their statistics
     */
    public function getWorkflowOverview(): GetWorkflowOverviewHandler
    {
        return new GetWorkflowOverviewHandler(
            $this->statistics ?? throw new LogicException('No WorkflowStatistics: give one with the repositories (withRepositories()).'),
        );
    }

    /**
     * @throws LogicException when no outbox sink was given
     */
    public function relayOutbox(): RelayOutboxHandler
    {
        return new RelayOutboxHandler(
            $this->outbox,
            $this->sink ?? throw new LogicException('No IntegrationEventSink: give the outbox and its sink (withOutbox()).'),
            $this->clock,
            $this->policy,
        );
    }

    public function purgeOutbox(): PurgeOutboxHandler
    {
        return new PurgeOutboxHandler($this->outbox, $this->clock);
    }
}
