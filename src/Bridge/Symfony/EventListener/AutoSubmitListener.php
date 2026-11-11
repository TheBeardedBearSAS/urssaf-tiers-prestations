<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\EventListener;

use Symfony\Component\Messenger\MessageBusInterface;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitCustomerRegistrationMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitPaymentRequestsMessage;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestCreated;

/**
 * Dispatches the submit messages as soon as a registration or a payment request is created (domain events are
 * published after the transaction commit, so the record is visible to the consumer).
 */
final readonly class AutoSubmitListener
{
    public function __construct(private MessageBusInterface $bus) {}

    public function onCustomerRegistrationRequested(CustomerRegistrationRequested $event): void
    {
        $this->bus->dispatch(new SubmitCustomerRegistrationMessage($event->registrationId()->value()));
    }

    public function onPaymentRequestCreated(PaymentRequestCreated $event): void
    {
        $this->bus->dispatch(new SubmitPaymentRequestsMessage($event->tenantId()->value(), [$event->paymentRequestId()->value()]));
    }
}
