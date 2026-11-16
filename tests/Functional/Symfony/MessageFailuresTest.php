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

use LogicException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitCustomerRegistrationMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantCustomerPairingMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\MessageFailures;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SubmitCustomerRegistrationMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SyncTenantCustomerPairingMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\RetryDelay;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Scenario;
use Throwable;

final class MessageFailuresTest extends TestCase
{
    private const MESSENGER = ['messenger' => ['enabled' => true, 'auto_submit' => true]];

    public function testATemporaryFailureIsRecoverableAndKeepsTheRetryDelayRequestedByUrssaf(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);
        $id = Scenario::pendingRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_REGISTER, 429, '{}', ['Retry-After' => '7']);
        $handler = $harness->privateService(SubmitCustomerRegistrationMessageHandler::class, SubmitCustomerRegistrationMessageHandler::class);

        try {
            $handler(new SubmitCustomerRegistrationMessage($id->value()));
            static::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $failure) {
            RetryDelay::assertCarries(7000, $failure);
            static::assertSame(0, $failure->getCode());
            static::assertInstanceOf(RetryableUrssafFailure::class, $failure->getPrevious());
            static::assertSame($failure->getPrevious()->getMessage(), $failure->getMessage());
        }
    }

    public function testATemporaryFailureWithoutDelayIsRecoverable(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);
        Scenario::pendingRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_REGISTER, 429, '{}');
        $handler = $harness->privateService(SubmitCustomerRegistrationMessageHandler::class, SubmitCustomerRegistrationMessageHandler::class);

        $this->expectException(RecoverableMessageHandlingException::class);

        $handler(new SubmitCustomerRegistrationMessage(self::pendingId($harness)));
    }

    public function testAPermanentFailureIsUnrecoverable(): void
    {
        $harness = Harness::boot(self::MESSENGER, false, true);
        Scenario::submittedRegistration($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{}');
        $handler = $harness->privateService(SyncTenantCustomerPairingMessageHandler::class, SyncTenantCustomerPairingMessageHandler::class);

        try {
            $handler(new SyncTenantCustomerPairingMessage('tenant-1'));
            static::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException $failure) {
            $previous = $failure->getPrevious();
            static::assertInstanceOf(UrssafAuthenticationFailed::class, $previous);
            static::assertSame(0, $failure->getCode());
            static::assertSame($previous->getMessage(), $failure->getMessage());
        }
    }

    public function testAnInvalidIdentifierIsUnrecoverable(): void
    {
        $handler = Harness::boot(self::MESSENGER, false, true)->privateService(SubmitCustomerRegistrationMessageHandler::class, SubmitCustomerRegistrationMessageHandler::class);

        try {
            $handler(new SubmitCustomerRegistrationMessage(' '));
            static::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException $failure) {
            static::assertInstanceOf(InvalidValue::class, $failure->getPrevious());
        }
    }

    public function testTheRetryDelayComesFromTheLocalRateLimiterToo(): void
    {
        $failure = self::caught(static function (): void {
            MessageFailures::guard(static function (): never {
                throw RateLimitExceeded::forOperation(Operation::RegisterCustomer, 3);
            });
        });

        static::assertInstanceOf(RecoverableMessageHandlingException::class, $failure);
        RetryDelay::assertCarries(3000, $failure);
    }

    public function testThereIsNoRetryDelayForATransportFailure(): void
    {
        $transport = UrssafUnavailable::dueToTransportError(Operation::RegisterCustomer, new LogicException('refused'));

        $failure = self::caught(static function () use ($transport): void {
            MessageFailures::guard(static function () use ($transport): never {
                throw $transport;
            });
        });

        static::assertInstanceOf(RecoverableMessageHandlingException::class, $failure);
        RetryDelay::assertCarries(null, $failure);
        static::assertSame($transport, $failure->getPrevious());
    }

    public function testFailuresOfOtherOriginsAreLeftUntouchedAndResultsAreReturned(): void
    {
        $original = new LogicException('database down');

        $failure = self::caught(static function () use ($original): void {
            MessageFailures::guard(static function () use ($original): never {
                throw $original;
            });
        });

        static::assertSame($original, $failure);
        static::assertSame('done', MessageFailures::guard(static fn(): string => 'done'));
    }

    /**
     * @param callable(): void $operation
     * @param-immediately-invoked-callable $operation
     */
    private static function caught(callable $operation): Throwable
    {
        try {
            $operation();
        } catch (Throwable $failure) {
            return $failure;
        }

        static::fail('Nothing was thrown.');
    }

    private static function pendingId(Harness $harness): string
    {
        $envelope = self::transport($harness)->getSent()[0];
        Assert::assertInstanceOf(Envelope::class, $envelope);
        $message = $envelope->getMessage();
        Assert::assertInstanceOf(SubmitCustomerRegistrationMessage::class, $message);

        return $message->registrationId;
    }

    private static function transport(Harness $harness): InMemoryTransport
    {
        return $harness->privateService('messenger.transport.async', InMemoryTransport::class);
    }
}
