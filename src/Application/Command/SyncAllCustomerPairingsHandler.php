<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;

/**
 * Runs {@see SyncCustomerPairingHandler} for each tenant having registrations awaiting the pairing. A tenant whose
 * credentials are refused is reported ({@see ItemOutcome::InvalidCredentials}, subject = tenant id) and the run goes
 * on with the next tenant; temporary failures propagate.
 */
final readonly class SyncAllCustomerPairingsHandler
{
    public function __construct(
        private CustomerRegistrationRepository $registrations,
        private SyncCustomerPairingHandler $syncTenant,
    ) {}

    /**
     * @throws RetryableException
     */
    public function __invoke(SyncAllCustomerPairings $command): BatchReport
    {
        $report = BatchReport::of();
        foreach ($this->registrations->tenantsWithPairingToFollow() as $tenantId) {
            try {
                $tenantReport = ($this->syncTenant)(new SyncCustomerPairing($tenantId, $command->limitPerTenant));
            } catch (UrssafAuthenticationFailed) {
                $tenantReport = BatchReport::of(new BatchItem($tenantId->value(), ItemOutcome::InvalidCredentials));
            }
            $report = $report->merge($tenantReport);
        }

        return $report;
    }
}
