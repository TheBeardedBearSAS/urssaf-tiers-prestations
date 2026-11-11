<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

/**
 * Shared scenario of the customer registration submission tests.
 */
final class RegistrationSubmissionScenario
{
    public const LATER = '2022-03-01T00:05:00Z';

    public static function harness(WorkflowPolicy $policy = new WorkflowPolicy()): Harness
    {
        $harness = new Harness($policy);
        $harness->registration(1, RegistrationState::Pending);
        $harness->clock->moveTo(self::LATER);

        return $harness;
    }

    public static function submit(Harness $harness): ItemOutcome
    {
        $handler = new SubmitCustomerRegistrationHandler($harness->registrations, $harness->gateway, $harness->clock, $harness->unitOfWork, $harness->policy);

        return $handler(new SubmitCustomerRegistration(RegistrationId::fromString('reg-1')));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function causes(CustomerRegistration $registration): array
    {
        return array_map(
            static fn(RejectionCause $cause): array => [$cause->code(), $cause->message()],
            $registration->status()->lastErrors(),
        );
    }
}
