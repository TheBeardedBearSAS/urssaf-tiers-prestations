<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Application\Service;

use TheBeardedBear\UrssafTiersPrestations\Contract\Event\CustomerPairingChanged;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\CustomerRegistrationRejected;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\CustomerRegistrationSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingValidated;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRejected as DomainCustomerRegistrationRejected;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerSubmittedToUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\DomainEvent;

/**
 * Integration events of the customer registration workflow.
 */
final class CustomerEventTranslator
{
    /**
     * @return list<IntegrationEvent> the integration event of the domain event, none when it has none
     */
    public static function translate(DomainEvent $event): array
    {
        return match (true) {
            $event instanceof CustomerSubmittedToUrssaf => [self::customerSubmitted($event)],
            $event instanceof CustomerPairingValidated => [self::pairingValidated($event)],
            $event instanceof CustomerPairingStatusChanged => [self::pairingChanged($event)],
            $event instanceof DomainCustomerRegistrationRejected => [self::customerRejected($event)],
            default => [],
        };
    }

    private static function customerSubmitted(CustomerSubmittedToUrssaf $event): CustomerRegistrationSubmitted
    {
        $aggregateId = $event->registrationId()->value();
        $urssafCustomerId = $event->urssafCustomerId()->value();

        return new CustomerRegistrationSubmitted(
            IntegrationEventFacts::id(CustomerRegistrationSubmitted::TYPE, $aggregateId, $event->occurredAt(), [$urssafCustomerId]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $urssafCustomerId,
        );
    }

    private static function pairingValidated(CustomerPairingValidated $event): CustomerPairingChanged
    {
        $aggregateId = $event->registrationId()->value();

        return new CustomerPairingChanged(
            IntegrationEventFacts::id(CustomerPairingChanged::TYPE, $aggregateId, $event->occurredAt(), [PairingStatus::Validated->value]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            PairingStatus::Validated->value,
            RegistrationState::Registered->value,
            true,
        );
    }

    private static function pairingChanged(CustomerPairingStatusChanged $event): CustomerPairingChanged
    {
        $aggregateId = $event->registrationId()->value();
        $state = $event->newState();

        return new CustomerPairingChanged(
            IntegrationEventFacts::id(CustomerPairingChanged::TYPE, $aggregateId, $event->occurredAt(), [$event->pairingStatus()->value, $state->value]),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $event->pairingStatus()->value,
            $state->value,
            $state === RegistrationState::Registered,
        );
    }

    private static function customerRejected(DomainCustomerRegistrationRejected $event): CustomerRegistrationRejected
    {
        $aggregateId = $event->registrationId()->value();
        $codes = array_map(static fn(RejectionCause $cause): string => $cause->code(), $event->causes());

        return new CustomerRegistrationRejected(
            IntegrationEventFacts::id(CustomerRegistrationRejected::TYPE, $aggregateId, $event->occurredAt(), $codes),
            IntegrationEventFacts::instant($event->occurredAt()),
            $event->tenantId()->value(),
            $aggregateId,
            $event->externalReference()->value(),
            $codes,
        );
    }
}
