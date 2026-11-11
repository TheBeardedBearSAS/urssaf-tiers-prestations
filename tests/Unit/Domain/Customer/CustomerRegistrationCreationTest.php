<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Customer;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;

final class CustomerRegistrationCreationTest extends TestCase
{
    public function testRequest(): void
    {
        $registration = CustomerFixtures::newRegistration();

        static::assertSame('reg-1', $registration->id()->value());
        static::assertSame('tenant-1', $registration->tenantId()->value());
        static::assertSame('customer-42', $registration->externalReference()->value());
        static::assertSame('Durand', $registration->customer()->civilStatus()->birthName()->value());
        static::assertSame('2026-11-10T09:00:00Z', $registration->createdAt()->toIso8601());

        $status = $registration->status();
        static::assertSame(RegistrationState::Pending, $status->state());
        static::assertNull($status->urssafCustomerId());
        static::assertNull($status->pairingAcceptedAt());
        static::assertSame([], $status->lastErrors());
        static::assertSame('2026-11-10T09:00:00Z', $status->updatedAt()->toIso8601());
    }

    public function testRequestRecordsAnEvent(): void
    {
        $registration = CustomerFixtures::newRegistration();

        $events = $registration->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(CustomerRegistrationRequested::class, $event);
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('customer-42', $event->externalReference()->value());
        static::assertSame('2026-11-10T09:00:00+00:00', $event->occurredAt()->format(DATE_ATOM));
        static::assertSame([], $registration->releaseEvents());
    }

    /**
     * Birth date compared with "today" in Paris.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function providePastBirthDates(): iterable
    {
        yield 'yesterday' => ['2026-11-09', '2026-11-10T12:00:00Z'];
        yield 'today' => ['2026-11-10', '2026-11-10T12:00:00Z'];
        yield 'today in Paris, tomorrow in UTC' => ['2026-11-11', '2026-11-10T23:30:00Z'];
    }

    #[DataProvider('providePastBirthDates')]
    public function testBirthDateMayBeToday(string $birthDate, string $now): void
    {
        static::assertSame(RegistrationState::Pending, self::register(self::bornOn($birthDate), $now)->status()->state());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFutureBirthDates(): iterable
    {
        yield 'tomorrow' => ['2026-11-11', '2026-11-10T12:00:00Z'];
        yield 'tomorrow in Paris' => ['2026-11-12', '2026-11-10T23:30:00Z'];
    }

    #[DataProvider('provideFutureBirthDates')]
    public function testBirthDateMustNotBeInTheFuture(string $birthDate, string $now): void
    {
        $customer = self::bornOn($birthDate);

        $this->expectExceptionObject(InvariantViolation::because('birth_date.in_future', 'The birth date must not be in the future.'));

        self::register($customer, $now);
    }

    private static function bornOn(string $birthDate): Customer
    {
        $official = CustomerFixtures::officialDraft();
        return (new CustomerFactory())->fromDraft(new CustomerDraft(
            $official->civility,
            $official->birthName,
            $official->usageName,
            $official->firstNames,
            $birthDate,
            $official->birthPlace,
            $official->mobilePhone,
            $official->email,
            $official->address,
            $official->bankAccount,
        ));
    }

    private static function register(Customer $customer, string $now): CustomerRegistration
    {
        return CustomerRegistration::request(
            RegistrationId::fromString('reg-1'),
            TenantId::fromString('tenant-1'),
            ExternalReference::fromString('customer-42'),
            $customer,
            CustomerFixtures::at($now),
        );
    }
}
