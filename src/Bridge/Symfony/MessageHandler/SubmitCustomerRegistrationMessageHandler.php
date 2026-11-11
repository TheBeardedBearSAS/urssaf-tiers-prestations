<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\MessageHandler;

use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitCustomerRegistrationHandler;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Message\SubmitCustomerRegistrationMessage;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;

final readonly class SubmitCustomerRegistrationMessageHandler
{
    public function __construct(private SubmitCustomerRegistrationHandler $submit, private OperationLocks $locks) {}

    public function __invoke(SubmitCustomerRegistrationMessage $message): void
    {
        $this->locks->exclusively(
            'customers.submit',
            $message->registrationId,
            fn(): mixed => MessageFailures::guard(fn(): mixed => ($this->submit)(new SubmitCustomerRegistration(RegistrationId::fromString($message->registrationId)))),
            static fn(string $resource): never => throw MessageFailures::busy($resource),
        );
    }
}
