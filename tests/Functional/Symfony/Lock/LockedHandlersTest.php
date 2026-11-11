<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Lock;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitCustomerRegistrationMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitPaymentRequestsMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantCustomerPairingMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantPaymentStatusesMessage;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SubmitCustomerRegistrationMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SubmitPaymentRequestsMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SyncTenantCustomerPairingMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler\SyncTenantPaymentStatusesMessageHandler;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\HeldLocks;

final class LockedHandlersTest extends TestCase
{
    /**
     * @param Closure(Harness): void $handle
     */
    #[DataProvider('messages')]
    public function testAHandlerAsksForARetryWhenItsOperationIsAlreadyRunning(Closure $handle, string $resource): void
    {
        $harness = Harness::boot(['messenger' => ['enabled' => true]], false, true, true);
        $held = HeldLocks::hold($harness, $resource);

        try {
            $handle($harness);
            static::fail('The handler must ask for a retry.');
        } catch (RecoverableMessageHandlingException $retry) {
            static::assertSame(30_000, $retry->getRetryDelay());
            static::assertSame(0, $retry->getCode());
            static::assertStringContainsString(\sprintf('lock "%s" is held', $resource), $retry->getMessage());
        }
        static::assertSame([], $harness->client->getRequests());
        static::assertTrue($held->isAcquired());
    }

    /**
     * @return iterable<string, array{Closure(Harness): void, string}>
     */
    public static function messages(): iterable
    {
        yield 'submit customer' => [
            static function (Harness $harness): void {
                $harness->privateService(SubmitCustomerRegistrationMessageHandler::class, SubmitCustomerRegistrationMessageHandler::class)(new SubmitCustomerRegistrationMessage('registration-1'));
            },
            'urssaf_tps.customers.submit.registration-1',
        ];
        yield 'submit payment requests' => [
            static function (Harness $harness): void {
                $harness->privateService(SubmitPaymentRequestsMessageHandler::class, SubmitPaymentRequestsMessageHandler::class)(new SubmitPaymentRequestsMessage('tenant-1', ['pr-1']));
            },
            'urssaf_tps.payment-requests.submit.tenant-1',
        ];
        yield 'sync pairing' => [
            static function (Harness $harness): void {
                $harness->privateService(SyncTenantCustomerPairingMessageHandler::class, SyncTenantCustomerPairingMessageHandler::class)(new SyncTenantCustomerPairingMessage('tenant-1'));
            },
            'urssaf_tps.customers.sync-pairing.tenant-1',
        ];
        yield 'sync statuses' => [
            static function (Harness $harness): void {
                $harness->privateService(SyncTenantPaymentStatusesMessageHandler::class, SyncTenantPaymentStatusesMessageHandler::class)(new SyncTenantPaymentStatusesMessage('tenant-1'));
            },
            'urssaf_tps.payment-requests.sync-statuses.tenant-1',
        ];
    }
}
