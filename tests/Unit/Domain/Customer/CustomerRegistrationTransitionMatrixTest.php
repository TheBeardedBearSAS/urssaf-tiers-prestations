<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;

/**
 * Every command applied to a registration in every state.
 */
final class CustomerRegistrationTransitionMatrixTest extends TestCase
{
    private const PAIRING_STATES = ['awaiting_pairing', 'registered', 'blocked', 'mandate_expired'];

    /**
     * Command => [states from which it is allowed, resulting state ("" = depends on the pairing status)].
     */
    private const COMMANDS = [
        'startSubmission' => [['pending'], 'submitting'],
        'markSubmitted' => [['submitting'], 'awaiting_pairing'],
        'markRejected' => [['submitting', 'awaiting_pairing', 'registered', 'blocked', 'mandate_expired'], 'rejected'],
        'markFailed' => [['submitting'], 'error'],
        'markInvalidCredentials' => [['submitting'], 'invalid_credentials'],
        'retry' => [['error', 'invalid_credentials'], 'pending'],
        'APPAREILLAGE_VALIDE' => [self::PAIRING_STATES, 'registered'],
        'APPAREILLAGE_EC' => [self::PAIRING_STATES, 'awaiting_pairing'],
        'APPAREILLAGE_NF' => [self::PAIRING_STATES, 'awaiting_pairing'],
        'PARTICULIER_BLOQUE' => [self::PAIRING_STATES, 'blocked'],
        'MANDAT_ECHU' => [self::PAIRING_STATES, 'mandate_expired'],
    ];

    /**
     * @return iterable<string, array{RegistrationState, string}>
     */
    public static function provideMatrix(): iterable
    {
        foreach (RegistrationState::cases() as $state) {
            foreach (array_keys(self::COMMANDS) as $command) {
                yield $state->value . ' / ' . $command => [$state, $command];
            }
        }
    }

    #[DataProvider('provideMatrix')]
    public function testMatrix(RegistrationState $state, string $command): void
    {
        [$allowedFrom, $result] = self::COMMANDS[$command];
        $registration = CustomerFixtures::registrationIn($state);

        if (!\in_array($state->value, $allowedFrom, true)) {
            $exception = CustomerFixtures::assertViolationCode(
                'customer_registration.invalid_state_transition',
                static fn(): bool => self::apply($registration, $command),
            );
            static::assertInstanceOf(InvalidStateTransition::class, $exception);
            static::assertSame($state->value, $exception->from());
            static::assertSame($result, $exception->to());
            static::assertSame($state, $registration->status()->state());
            static::assertSame([], $registration->releaseEvents());

            return;
        }
        self::apply($registration, $command);
        static::assertSame($result, $registration->status()->state()->value);
    }

    private static function apply(CustomerRegistration $registration, string $command): bool
    {
        $now = CustomerFixtures::now();
        match ($command) {
            'startSubmission' => $registration->startSubmission($now),
            'markSubmitted' => $registration->markSubmitted(CustomerFixtures::urssafId(), $now),
            'markRejected' => $registration->markRejected([CustomerFixtures::cause()], $now),
            'markFailed' => $registration->markFailed([CustomerFixtures::cause()], $now),
            'markInvalidCredentials' => $registration->markInvalidCredentials($now),
            'retry' => $registration->retry($now),
            default => $registration->applyPairingStatus(
                PairingStatus::from($command),
                CustomerFixtures::at(CustomerFixtures::ACCEPTED_AT),
                $now,
            ),
        };

        return true;
    }
}
