<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Clock;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\TransmissionStatusReader;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UrssafErrorTranslator;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistrationRepository;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\RetryableException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Polls method 020 for the registrations of a tenant in state awaiting_pairing, blocked or mandate_expired (least
 * recently updated first, at most "limit" in total). Registered customers are not polled: a blocked account, an
 * expired mandate or a missing pairing is reported by method 050 when a payment request is submitted, which moves the
 * registration back to one of the polled states.
 *
 * Per registration (each one in its own transaction):
 * - known status -> applied ({@see ItemOutcome::Changed} when it recorded an event, {@see ItemOutcome::Unchanged});
 * - status code unknown to this package version -> left untouched ({@see ItemOutcome::UnknownStatus});
 * - no active registration with this provider any more ({@see UrssafRequestRejected::isNoActiveRegistration()}) ->
 *   rejected, final;
 * - other HTTP 400 -> left untouched ({@see ItemOutcome::Failed}).
 * Credential failures ({@see UrssafAuthenticationFailed}) and temporary failures ({@see RetryableException}) stop
 * the run and propagate: what was already processed stays committed.
 */
final readonly class SyncCustomerPairingHandler
{
    public function __construct(
        private CustomerRegistrationRepository $registrations,
        private TransmissionStatusReader $reader,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
        private WorkflowPolicy $policy,
    ) {}

    /**
     * @throws UrssafAuthenticationFailed
     * @throws RetryableException
     */
    public function __invoke(SyncCustomerPairing $command): BatchReport
    {
        $registrations = $this->registrations->findPairingToFollow($command->tenantId, $this->policy->pageSize($command->limit));

        return BatchReport::of(...array_map($this->sync(...), $registrations));
    }

    private function sync(CustomerRegistration $registration): BatchItem
    {
        $subject = $registration->id()->value();
        // Guaranteed by the invariants of the states following the pairing status.
        /** @var UrssafCustomerId $urssafCustomerId */
        $urssafCustomerId = $registration->status()->urssafCustomerId();

        try {
            $status = $this->reader->transmissionStatus($registration->tenantId(), $urssafCustomerId);
        } catch (UrssafRequestRejected $rejection) {
            if (!$rejection->isNoActiveRegistration()) {
                return new BatchItem($subject, ItemOutcome::Failed, $rejection->errors()->codes());
            }
            $causes = UrssafErrorTranslator::rejectionCauses($rejection->errors());
            $this->save($registration, static fn(Instant $now) => $registration->markRejected($causes, $now));

            return new BatchItem($subject, ItemOutcome::Rejected, $rejection->errors()->codes());
        }

        return $this->apply($registration, $status);
    }

    private function apply(CustomerRegistration $registration, CustomerTransmissionStatus $status): BatchItem
    {
        $subject = $registration->id()->value();
        $pairingStatus = $status->pairingStatus();
        if (!$pairingStatus instanceof PairingStatus) {
            return new BatchItem($subject, ItemOutcome::UnknownStatus, [$status->code()]);
        }
        $changed = $this->save(
            $registration,
            static fn(Instant $now) => $registration->applyPairingStatus($pairingStatus, $status->pairingAcceptedAt(), $now),
        );

        return new BatchItem($subject, $changed ? ItemOutcome::Changed : ItemOutcome::Unchanged, [$status->code()]);
    }

    /**
     * Applies the change, saves the registration and records its events; true when an event was recorded.
     *
     * @param callable(Instant): void $change
     * @param-immediately-invoked-callable $change
     */
    private function save(CustomerRegistration $registration, callable $change): bool
    {
        return $this->unitOfWork->run(function (RecordedEvents $events) use ($registration, $change): bool {
            $change(Instant::fromDateTime($this->clock->now()));
            $this->registrations->save($registration);
            $released = $registration->releaseEvents();
            $events->record(...$released);

            return $released !== [];
        });
    }
}
