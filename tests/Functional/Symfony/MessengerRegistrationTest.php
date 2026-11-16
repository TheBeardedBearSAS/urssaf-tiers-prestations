<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\CustomerSelector;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\FindPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Query\GetCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\EventListener\AutoSubmitListener;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitCustomerRegistrationMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitPaymentRequestsMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantCustomerPairingMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantPaymentStatusesMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SubmitCustomerRegistrationMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SubmitPaymentRequestsMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SyncTenantCustomerPairingMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SyncTenantPaymentStatusesMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;

final class MessengerRegistrationTest extends TestCase
{
    private const MESSENGER = ['messenger' => ['enabled' => true, 'auto_submit' => true]];

    public function testNothingIsRegisteredWhenMessengerIsDisabled(): void
    {
        $harness = Harness::boot();

        static::assertFalse($harness->hasPrivate(SubmitCustomerRegistrationMessageHandler::class));
        static::assertFalse($harness->hasPrivate(AutoSubmitListener::class));
    }

    public function testTheHandlersAreRegisteredWithoutTheListenerWhenAutoSubmitIsOff(): void
    {
        $harness = Harness::boot(['messenger' => ['enabled' => true]], false, true);

        foreach ([SubmitCustomerRegistrationMessageHandler::class, SubmitPaymentRequestsMessageHandler::class, SyncTenantPaymentStatusesMessageHandler::class, SyncTenantCustomerPairingMessageHandler::class] as $handler) {
            static::assertTrue($harness->hasPrivate($handler), $handler);
        }
        static::assertFalse($harness->hasPrivate(AutoSubmitListener::class));
        Scenario::pendingRegistration($harness);
        static::assertSame([], self::transport($harness)->getSent());
    }

    public function testTheMessagesAreDispatchedWhenARegistrationAndAPaymentRequestAreCreated(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);

        $registrationId = Scenario::pendingRegistration($harness);
        $sent = self::transport($harness)->getSent();
        static::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        static::assertInstanceOf(SubmitCustomerRegistrationMessage::class, $message);
        static::assertSame($registrationId->value(), $message->registrationId);

        // The consumer submits it, then the customer is paired and a payment request is created.
        $harness->privateService(SubmitCustomerRegistrationMessageHandler::class, SubmitCustomerRegistrationMessageHandler::class)($message);
        static::assertSame('awaiting_pairing', self::registrationState($harness, $registrationId));
        $harness->privateService(SyncTenantCustomerPairingMessageHandler::class, SyncTenantCustomerPairingMessageHandler::class)(new SyncTenantCustomerPairingMessage('tenant-1'));
        static::assertSame('registered', self::registrationState($harness, $registrationId));

        $paymentRequestId = Scenario::createPaymentRequest($harness);
        $sent = self::transport($harness)->getSent();
        static::assertCount(2, $sent);
        $message = $sent[1]->getMessage();
        static::assertInstanceOf(SubmitPaymentRequestsMessage::class, $message);
        static::assertSame('tenant-1', $message->tenantId);
        static::assertSame([$paymentRequestId->value()], $message->paymentRequestIds);

        $harness->privateService(SubmitPaymentRequestsMessageHandler::class, SubmitPaymentRequestsMessageHandler::class)($message);
        static::assertSame('submitted', self::paymentRequestState($harness, $paymentRequestId));
        $harness->privateService(SyncTenantPaymentStatusesMessageHandler::class, SyncTenantPaymentStatusesMessageHandler::class)(new SyncTenantPaymentStatusesMessage('tenant-1'));
        static::assertSame('70', $harness->get(FindPaymentRequestsHandler::class)(FindPaymentRequests::byIds(TenantId::fromString('tenant-1'), $paymentRequestId))[0]->urssaf->status);
    }

    private static function transport(Harness $harness): InMemoryTransport
    {
        return $harness->privateService('messenger.transport.async', InMemoryTransport::class);
    }

    private static function registrationState(Harness $harness, RegistrationId $id): string
    {
        return $harness->get(GetCustomerRegistrationHandler::class)(
            new GetCustomerRegistration(TenantId::fromString('tenant-1'), CustomerSelector::byRegistrationId($id)),
        )->state;
    }

    private static function paymentRequestState(Harness $harness, LocalPaymentRequestId $id): string
    {
        return $harness->get(FindPaymentRequestsHandler::class)(FindPaymentRequests::byIds(TenantId::fromString('tenant-1'), $id))[0]->state;
    }
}
