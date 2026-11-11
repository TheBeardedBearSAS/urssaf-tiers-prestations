<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\ReconcileStaleRegistrationsCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\ReconcileStaleSubmissionsCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\RequeueInvalidCredentialsCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\SubmitPendingCustomersCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\SubmitPendingPaymentRequestsCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\SyncCustomerPairingCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Command\SyncPaymentStatusesCommand;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\EventListener\AutoSubmitListener;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SubmitCustomerRegistrationMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SubmitPaymentRequestsMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SyncTenantCustomerPairingMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SyncTenantPaymentStatusesMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\UrssafTiersPrestationsBundle;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestCreated;

/**
 * Driving adapters: console commands, Messenger handlers and the optional auto-submit listener.
 */
final readonly class EntryPointRegistrar
{
    private const COMMANDS = [
        SyncCustomerPairingCommand::class,
        SyncPaymentStatusesCommand::class,
        ReconcileStaleRegistrationsCommand::class,
        ReconcileStaleSubmissionsCommand::class,
        RequeueInvalidCredentialsCommand::class,
    ];

    /** Commands that fall back on the configured tenants. */
    private const TENANT_COMMANDS = [
        SubmitPendingCustomersCommand::class,
        SubmitPendingPaymentRequestsCommand::class,
    ];

    private const MESSAGE_HANDLERS = [
        SubmitCustomerRegistrationMessageHandler::class,
        SubmitPaymentRequestsMessageHandler::class,
        SyncTenantPaymentStatusesMessageHandler::class,
        SyncTenantCustomerPairingMessageHandler::class,
    ];

    public function __construct(private ServicesConfigurator $services) {}

    public function registerConsole(): void
    {
        foreach (self::COMMANDS as $command) {
            $this->services->set($command)->autowire()->tag('console.command');
        }
        foreach (self::TENANT_COMMANDS as $command) {
            $this->services->set($command)
                ->autowire()
                ->arg('$configuredTenants', '%' . UrssafTiersPrestationsBundle::PARAMETER_TENANTS . '%')
                ->tag('console.command');
        }
    }

    /**
     * @param array{enabled: bool, bus: string, auto_submit: bool} $messenger
     */
    public function registerMessenger(array $messenger): void
    {
        foreach (self::MESSAGE_HANDLERS as $handler) {
            $this->services->set($handler)->autowire()->tag('messenger.message_handler');
        }
        if ($messenger['auto_submit']) {
            $this->services->set(AutoSubmitListener::class)
                ->args([new Reference($messenger['bus'])])
                ->tag('kernel.event_listener', ['event' => CustomerRegistrationRequested::class, 'method' => 'onCustomerRegistrationRequested'])
                ->tag('kernel.event_listener', ['event' => PaymentRequestCreated::class, 'method' => 'onPaymentRequestCreated']);
        }
    }
}
