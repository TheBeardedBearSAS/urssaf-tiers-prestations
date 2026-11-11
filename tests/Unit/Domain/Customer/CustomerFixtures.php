<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use Closure;
use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Customer;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\AddressDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BankAccountDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\BirthPlaceDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Input\CustomerFactory;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;

/**
 * Shared test data: the official request example of method 010 (docs/api-reference.md section 2.1).
 */
final class CustomerFixtures
{
    public const URSSAF_ID = 'af0be430-908a-11eb-a8b3-0242ac130003';
    public const NOW = '2026-11-11T10:00:00Z';
    public const ACCEPTED_AT = '2026-11-01T08:30:00Z';

    public static function officialDraft(): CustomerDraft
    {
        return new CustomerDraft(
            '1',
            'Durand',
            'Martin',
            'Eric-Antoine',
            '1980-03-29',
            new BirthPlaceDraft('99100', '069', '001', 'Lyon'),
            '0605040302',
            'jeanne.durand@contact.fr',
            self::officialAddress(),
            new BankAccountDraft('FR7630006000011234567890189', 'BNPAFRPPXXX', 'Mme Jeanne Martin'),
        );
    }

    public static function officialAddress(): AddressDraft
    {
        return new AddressDraft('8', 'B', 'R', 'du Soleil', 'Batiment A', 'Le Beyssat', 'LYON 01', '69101', '69001', '99100');
    }

    public static function customer(): Customer
    {
        return (new CustomerFactory())->fromDraft(self::officialDraft());
    }

    public static function at(string $iso): Instant
    {
        return Instant::fromIso8601($iso);
    }

    public static function now(): Instant
    {
        return self::at(self::NOW);
    }

    public static function urssafId(): UrssafCustomerId
    {
        return UrssafCustomerId::fromString(self::URSSAF_ID);
    }

    public static function newRegistration(): CustomerRegistration
    {
        return CustomerRegistration::request(
            RegistrationId::fromString('reg-1'),
            TenantId::fromString('tenant-1'),
            ExternalReference::fromString('customer-42'),
            self::customer(),
            self::at('2026-11-10T09:00:00Z'),
        );
    }

    /**
     * Registration driven to the given state through the public API; recorded events are released.
     */
    public static function registrationIn(RegistrationState $state): CustomerRegistration
    {
        $registration = self::newRegistration();
        $now = self::now();
        $steps = match ($state) {
            RegistrationState::Pending => [],
            RegistrationState::Submitting => ['start'],
            RegistrationState::AwaitingPairing => ['start', 'submitted'],
            RegistrationState::Registered => ['start', 'submitted', PairingStatus::Validated],
            RegistrationState::Blocked => ['start', 'submitted', PairingStatus::Validated, PairingStatus::CustomerBlocked],
            RegistrationState::MandateExpired => ['start', 'submitted', PairingStatus::Validated, PairingStatus::MandateExpired],
            RegistrationState::Rejected => ['start', 'rejected'],
            RegistrationState::Error => ['start', 'failed'],
            RegistrationState::InvalidCredentials => ['start', 'credentials'],
        };
        foreach ($steps as $step) {
            match ($step) {
                'start' => $registration->startSubmission($now),
                'submitted' => $registration->markSubmitted(self::urssafId(), $now),
                'rejected' => $registration->markRejected([self::cause()], $now),
                'failed' => $registration->markFailed([self::cause()], $now),
                'credentials' => $registration->markInvalidCredentials($now),
                default => $registration->applyPairingStatus($step, self::at(self::ACCEPTED_AT), $now),
            };
        }
        $registration->releaseEvents();

        return $registration;
    }

    public static function cause(): RejectionCause
    {
        return RejectionCause::of('ERR_DGFIP_R2P_INCONNU', 'Unknown identity.');
    }

    /**
     * Runs the action and asserts it throws a domain exception with the given violation code.
     *
     * @param Closure(): mixed $action
     *
     * @param-immediately-invoked-callable $action
     */
    public static function assertViolationCode(string $expectedCode, Closure $action): DomainException
    {
        try {
            $action();
        } catch (DomainException $exception) {
            Assert::assertSame($expectedCode, $exception->violationCode());

            return $exception;
        }
        Assert::fail(\sprintf('Expected a domain exception with code "%s".', $expectedCode));
    }
}
