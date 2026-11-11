<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairing;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SyncCustomerPairingHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RefusalReason;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

/**
 * Shared scenario of the customer pairing tests.
 */
final class PairingScenario
{
    public const LATER = '2022-03-01T00:05:00Z';
    public const ACCEPTED_AT = '2022-02-20T09:00:00Z';

    public static function willReport(Harness $harness, int $number, string $code): void
    {
        $id = Harness::urssafCustomerId($number);
        $harness->gateway->willReportStatus($id, $code === 'APPAREILLAGE_VALIDE'
            ? CustomerTransmissionStatus::allowed($id, $code, 'd', Harness::at(self::ACCEPTED_AT))
            : CustomerTransmissionStatus::blocked($id, $code, 'd'));
    }

    public static function rejected(string $code, string $message, RefusalReason $reason = RefusalReason::Generic): UrssafRequestRejected
    {
        return UrssafRequestRejected::withErrors(Operation::GetCustomerStatus, 400, ApiErrors::of(ApiError::of($code, $message, 'd')), $reason);
    }

    public static function handler(Harness $harness): SyncCustomerPairingHandler
    {
        return new SyncCustomerPairingHandler($harness->registrations, $harness->gateway, $harness->clock, $harness->unitOfWork, $harness->policy);
    }

    public static function sync(Harness $harness, ?int $limit): BatchReport
    {
        return self::handler($harness)(new SyncCustomerPairing(Harness::tenant(), $limit));
    }

    /**
     * @return list<string>
     */
    public static function subjects(BatchReport $report): array
    {
        return array_map(static fn(BatchItem $item): string => $item->subject, $report->items());
    }
}
