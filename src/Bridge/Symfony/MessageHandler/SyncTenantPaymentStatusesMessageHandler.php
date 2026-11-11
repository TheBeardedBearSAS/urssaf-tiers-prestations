<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler;

use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatuses;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SyncTenantPaymentStatusesMessage;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

final readonly class SyncTenantPaymentStatusesMessageHandler
{
    public function __construct(private SyncPaymentRequestStatusesHandler $sync, private OperationLocks $locks) {}

    public function __invoke(SyncTenantPaymentStatusesMessage $message): void
    {
        $this->locks->exclusively(
            'payment-requests.sync-statuses',
            $message->tenantId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->sync)(new SyncPaymentRequestStatuses(TenantId::fromString($message->tenantId), null))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
