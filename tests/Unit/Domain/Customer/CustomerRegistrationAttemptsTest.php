<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Customer;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerRegistrationAnonymised;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * Submission attempts (count, maximum before a manual action), stale submissions and anonymisation.
 *
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class CustomerRegistrationAttemptsTest extends TestCase
{
    public function testEachSubmissionStartCountsAnAttempt(): void
    {
        $registration = CustomerFixtures::newRegistration();
        static::assertSame(0, $registration->status()->attempts());
        static::assertNull($registration->status()->lastAttemptAt());

        self::failOnce($registration, CustomerFixtures::at('2026-11-11T09:00:00Z'));
        $registration->retry(CustomerFixtures::now());
        $registration->startSubmission(CustomerFixtures::now());

        static::assertSame(2, $registration->status()->attempts());
        static::assertSame(CustomerFixtures::NOW, $registration->status()->lastAttemptAt()?->toIso8601());
    }

    public function testAFailureIsNotRetriedOnceTheMaximumNumberOfAttemptsIsReached(): void
    {
        $registration = CustomerFixtures::newRegistration();
        self::failOnce($registration, CustomerFixtures::now());
        static::assertTrue($registration->canRetry(2));
        $registration->retry(CustomerFixtures::now(), 2);
        self::failOnce($registration, CustomerFixtures::now());
        $registration->releaseEvents();

        static::assertFalse($registration->canRetry(2));
        static::assertTrue($registration->canRetry(3));
        $exception = CustomerFixtures::assertViolationCode('customer_registration.max_attempts_reached', static function () use ($registration): void {
            $registration->retry(CustomerFixtures::now(), 2);
        });

        static::assertInstanceOf(InvariantViolation::class, $exception);
        static::assertSame(['attempts' => 2, 'max_attempts' => 2], $exception->params());
        static::assertSame('The registration failed 2 times: it is not retried automatically any more.', $exception->getMessage());
        static::assertSame(RegistrationState::Error, $registration->status()->state());
        static::assertSame([], $registration->releaseEvents());
    }

    public function testTheDefaultMaximumIsFiveAttempts(): void
    {
        $registration = CustomerFixtures::newRegistration();
        for ($attempt = 1; $attempt < 5; ++$attempt) {
            self::failOnce($registration, CustomerFixtures::now());
            $registration->retry(CustomerFixtures::now());
        }
        self::failOnce($registration, CustomerFixtures::now());

        static::assertFalse($registration->canRetry());
        $this->expectException(InvariantViolation::class);
        $registration->retry(CustomerFixtures::now());
    }

    public function testRefusedCredentialsAreAlwaysRetried(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::InvalidCredentials);

        static::assertTrue($registration->canRetry(1));
        $registration->retry(CustomerFixtures::now(), 1);

        static::assertSame(RegistrationState::Pending, $registration->status()->state());
    }

    public function testCanRetryDoesNotCheckTheTransition(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Registered);

        static::assertTrue($registration->canRetry(1));
        $this->expectException(InvalidStateTransition::class);
        $registration->retry(CustomerFixtures::now(), 1);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideStaleness(): iterable
    {
        yield 'exactly stale' => ['2026-11-11T10:00:00Z', true];
        yield 'stale for longer' => ['2026-11-11T10:00:01Z', true];
        yield 'not stale yet' => ['2026-11-11T09:59:59Z', false];
    }

    #[DataProvider('provideStaleness')]
    public function testASubmissionIsStaleOnceItsLastAttemptStartedAtLeastTheGivenAgeAgo(string $now, bool $stale): void
    {
        $registration = CustomerFixtures::newRegistration();
        $registration->startSubmission(CustomerFixtures::at('2026-11-11T09:45:00Z'));

        static::assertSame($stale, $registration->isStaleSubmission(CustomerFixtures::at($now), new DateInterval('PT15M')));
    }

    public function testOnlyASubmittingRegistrationIsStale(): void
    {
        $registration = CustomerFixtures::newRegistration();
        $registration->startSubmission(CustomerFixtures::at('2026-11-01T00:00:00Z'));
        $registration->markFailed([CustomerFixtures::cause()], CustomerFixtures::at('2026-11-01T00:00:00Z'));

        static::assertFalse($registration->isStaleSubmission(CustomerFixtures::now(), new DateInterval('PT15M')));
        static::assertFalse(CustomerFixtures::newRegistration()->isStaleSubmission(CustomerFixtures::now(), new DateInterval('PT15M')));
    }

    public function testARejectedRegistrationIsAnonymisedOnce(): void
    {
        $registration = CustomerFixtures::registrationIn(RegistrationState::Rejected);
        $later = CustomerFixtures::at('2026-11-12T00:00:00Z');

        $registration->anonymise($later);

        static::assertTrue($registration->customer()->equals(Customer::anonymised()));
        static::assertSame(RegistrationState::Rejected, $registration->status()->state());
        static::assertEquals([CustomerFixtures::cause()], $registration->status()->lastErrors());
        static::assertSame('2026-11-12T00:00:00Z', $registration->status()->updatedAt()->toIso8601());
        static::assertSame('customer-42', $registration->externalReference()->value());
        $events = $registration->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(CustomerRegistrationAnonymised::class, $event);
        static::assertSame('reg-1', $event->registrationId()->value());
        static::assertSame('tenant-1', $event->tenantId()->value());
        static::assertSame('2026-11-12T00:00:00Z', Instant::fromDateTime($event->occurredAt())->toIso8601());

        $registration->anonymise(CustomerFixtures::at('2026-11-13T00:00:00Z'));

        static::assertSame([], $registration->releaseEvents());
        static::assertSame('2026-11-12T00:00:00Z', $registration->status()->updatedAt()->toIso8601());
    }

    /**
     * @return iterable<string, array{RegistrationState}>
     */
    public static function provideNotFinalStates(): iterable
    {
        foreach (RegistrationState::cases() as $state) {
            if ($state->isFinal()) {
                continue;
            }

            yield $state->value => [$state];
        }
    }

    #[DataProvider('provideNotFinalStates')]
    public function testOnlyARegistrationInAFinalStateCanBeAnonymised(RegistrationState $state): void
    {
        $registration = CustomerFixtures::registrationIn($state);

        $this->expectExceptionObject(InvalidStateTransition::between('customer_registration', $state->value, 'anonymised'));

        $registration->anonymise(CustomerFixtures::now());
    }

    public function testTheAnonymisedCustomerIdentifiesNobody(): void
    {
        $anonymised = Customer::anonymised();
        $civilStatus = $anonymised->civilStatus();
        $contact = $anonymised->contactDetails();

        static::assertSame(Customer::ANONYMISED, $civilStatus->birthName()->value());
        static::assertNull($civilStatus->usageName());
        static::assertSame(Customer::ANONYMISED, $civilStatus->firstNames()->value());
        static::assertSame('1900-01-01', $civilStatus->birthDate()->toString());
        static::assertSame('99999', $civilStatus->birthPlace()->country()->value());
        static::assertSame('0600000000', $contact->mobilePhone()->value());
        static::assertSame('anonymised@example.invalid', $contact->email()->value());
        static::assertSame(Customer::ANONYMISED, $contact->address()->street()->locality()?->value());
        static::assertSame(Customer::ANONYMISED, $contact->address()->municipalityName()->value());
        static::assertSame('00000', $contact->address()->postalCode()->value());
        static::assertSame('FR7600000000000000000000000', $anonymised->bankAccount()->iban()->value());
        static::assertSame(Customer::ANONYMISED, $anonymised->bankAccount()->holder()->value());
    }

    private static function failOnce(CustomerRegistration $registration, Instant $at): void
    {
        $registration->startSubmission($at);
        $registration->markFailed([CustomerFixtures::cause()], $at);
    }
}
