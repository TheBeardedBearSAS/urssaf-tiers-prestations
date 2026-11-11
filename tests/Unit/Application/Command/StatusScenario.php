<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatuses;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncPaymentRequestStatusesHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\StatusInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

/**
 * Shared scenario of the payment request status tests.
 */
final class StatusScenario
{
    public const LATER = '2022-03-02T00:00:00Z';

    public static function harness(int $count): Harness
    {
        $harness = new Harness();
        foreach (range(1, $count) as $number) {
            $harness->paymentRequest($number, SubmissionState::Submitted);
        }
        $harness->clock->moveTo(self::LATER);

        return $harness;
    }

    public static function snapshot(int $number, string $status, ?TransferInfo $transfer, ?RejectionInfo $rejection): PaymentRequestSnapshot
    {
        return PaymentRequestSnapshot::of(PaymentRequestId::fromString('urssaf-' . $number), 'INV-' . $number, StatusInfo::of($status, 'label'), $rejection, $transfer);
    }

    /**
     * @return list<list<string>>
     */
    public static function searchedIds(Harness $harness): array
    {
        return array_map(
            static fn(PaymentRequestSearch $search): array => array_map(static fn(PaymentRequestId $id): string => $id->value(), $search->paymentRequestIds()),
            $harness->gateway->searches(),
        );
    }

    public static function handler(Harness $harness): SyncPaymentRequestStatusesHandler
    {
        return new SyncPaymentRequestStatusesHandler($harness->paymentRequests, $harness->gateway, $harness->clock, $harness->unitOfWork, $harness->policy);
    }

    public static function sync(Harness $harness, ?int $limit): BatchReport
    {
        return self::handler($harness)(new SyncPaymentRequestStatuses(Harness::tenant(), $limit));
    }
}
