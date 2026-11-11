<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomer;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\RegisterCustomerHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\CustomerAlreadyRegistered;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

final class RegisterCustomerHandlerTest extends TestCase
{
    public function testStoresAPendingRegistrationAndPublishesItsEventAfterTheCommit(): void
    {
        $harness = new Harness();

        $id = self::handler($harness)(self::command('customer-1', CustomerFixtures::officialDraft()));

        static::assertSame('id-1', $id->value());
        $stored = $harness->registrations->stored('id-1');
        static::assertSame(RegistrationState::Pending, $stored->status()->state());
        static::assertSame('tenant-1', $stored->tenantId()->value());
        static::assertSame('customer-1', $stored->externalReference()->value());
        static::assertSame(Harness::NOW, $stored->createdAt()->toIso8601());
        static::assertSame(['CustomerRegistrationRequested'], $harness->events->names());
        static::assertSame(['begin', 'save:id-1', 'commit', 'publish:CustomerRegistrationRequested'], $harness->journal->entries());
    }

    public function testRegisteringTheSameCustomerAgainReturnsTheExistingRegistration(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Registered);

        $id = self::handler($harness)(self::command('customer-1', CustomerFixtures::officialDraft()));

        static::assertSame('reg-1', $id->value());
        static::assertSame([], $harness->journal->entries());
        static::assertSame('id-1', $harness->ids->generate());
    }

    public function testRefusesOtherDetailsForARegisteredExternalReference(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Pending);
        $draft = CustomerFixtures::officialDraft();
        $other = new CustomerDraft(
            $draft->civility,
            $draft->birthName,
            $draft->usageName,
            $draft->firstNames,
            $draft->birthDate,
            $draft->birthPlace,
            $draft->mobilePhone,
            'other@contact.fr',
            $draft->address,
            $draft->bankAccount,
        );

        try {
            self::handler($harness)(self::command('customer-1', $other));
            static::fail('Expected CustomerAlreadyRegistered.');
        } catch (CustomerAlreadyRegistered $exception) {
            static::assertSame(['external_reference' => 'customer-1', 'registration_id' => 'reg-1'], $exception->params());
        }
        static::assertSame([], $harness->journal->entries());
    }

    public function testTheExternalReferenceIsScopedByTenant(): void
    {
        $harness = new Harness();
        $harness->registration(1, RegistrationState::Pending, Harness::OTHER_TENANT);

        static::assertSame('id-1', self::handler($harness)(self::command('customer-1', CustomerFixtures::officialDraft()))->value());
    }

    public function testRefusesAnInvalidCustomer(): void
    {
        $harness = new Harness();
        $draft = new CustomerDraft(null, null, null, null, null, null, null, null, null, null);

        $this->expectException(ValidationFailed::class);

        try {
            self::handler($harness)(self::command('customer-1', $draft));
        } finally {
            static::assertSame([], $harness->journal->entries());
        }
    }

    public function testRefusesABirthDateInTheFuture(): void
    {
        $harness = new Harness();
        $harness->clock->moveTo('1980-03-28T12:00:00Z');

        try {
            self::handler($harness)(self::command('customer-1', CustomerFixtures::officialDraft()));
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('birth_date.in_future', $exception->violationCode());
        }
        static::assertSame([], $harness->journal->entries());
    }

    private static function command(string $externalReference, CustomerDraft $draft): RegisterCustomer
    {
        return new RegisterCustomer(Harness::tenant(), ExternalReference::fromString($externalReference), $draft);
    }

    private static function handler(Harness $harness): RegisterCustomerHandler
    {
        return new RegisterCustomerHandler($harness->registrations, $harness->ids, $harness->clock, $harness->unitOfWork);
    }
}
