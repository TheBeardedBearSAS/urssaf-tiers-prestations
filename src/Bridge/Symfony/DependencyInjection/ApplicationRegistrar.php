<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use DateInterval;
use DateTimeZone;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\CreatePaymentRequestHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleRegistrationsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleSubmissionsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RequeueAfterInvalidCredentialsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPendingPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllCustomerPairingsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncAllPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairingHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\FailureReporter;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\PaymentRequestBuilder;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RegistrationLocator;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Logging\PsrLoggerFailureReporter;

/**
 * Application layer: workflow policy, services and use case handlers (the only public services).
 */
final readonly class ApplicationRegistrar
{
    /** Use case handlers exposed to the host application. */
    private const HANDLERS = [
        RegisterCustomerHandler::class,
        SubmitCustomerRegistrationHandler::class,
        SyncCustomerPairingHandler::class,
        SyncAllCustomerPairingsHandler::class,
        CreatePaymentRequestHandler::class,
        SubmitPaymentRequestsHandler::class,
        SubmitPendingPaymentRequestsHandler::class,
        SyncPaymentRequestStatusesHandler::class,
        SyncAllPaymentRequestStatusesHandler::class,
        ReconcileStaleRegistrationsHandler::class,
        ReconcileStaleSubmissionsHandler::class,
        RequeueAfterInvalidCredentialsHandler::class,
        GetCustomerRegistrationHandler::class,
        FindPaymentRequestsHandler::class,
    ];

    private const LOGGER_TAG = 'monolog.logger';
    private const LOGGER_CHANNEL = 'urssaf';

    public function __construct(private ServicesConfigurator $services) {}

    /**
     * @param array{business_timezone: string, settled_max_age: string, stale_submission_after: string, billing_third_party_id: string, batch_size: int, default_page_size: int, max_page_size: int, max_submission_attempts: int, unknown_status_max_age: string, max_polling_age: string} $workflow
     */
    public function register(array $workflow): void
    {
        $this->services->set(WorkflowPolicy::class)->args([
            new Definition(DateTimeZone::class, [$workflow['business_timezone']]),
            new Definition(DateInterval::class, [$workflow['settled_max_age']]),
            new Definition(DateInterval::class, [$workflow['stale_submission_after']]),
            $workflow['billing_third_party_id'],
            $workflow['batch_size'],
            $workflow['default_page_size'],
            $workflow['max_page_size'],
            $workflow['max_submission_attempts'],
            new Definition(DateInterval::class, [$workflow['unknown_status_max_age']]),
            new Definition(DateInterval::class, [$workflow['max_polling_age']]),
        ]);
        $this->services->set(FailureReporter::class, PsrLoggerFailureReporter::class)
            ->args([new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
            ->tag(self::LOGGER_TAG, ['channel' => self::LOGGER_CHANNEL]);
        foreach ([UnitOfWork::class, RegistrationLocator::class, PaymentRequestBuilder::class] as $service) {
            $this->services->set($service)->autowire();
        }
        foreach (self::HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->public();
        }
    }
}
