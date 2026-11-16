<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;

final class SimpleCustomerValuesTest extends TestCase
{
    public function testRegistrationId(): void
    {
        $id = RegistrationId::fromString('reg-1');

        static::assertSame('reg-1', $id->value());
        static::assertSame('reg-1', (string) $id);
        static::assertTrue($id->equals(RegistrationId::fromString('reg-1')));
        static::assertFalse($id->equals(RegistrationId::fromString('reg-2')));
        static::assertSame(str_repeat('é', 64), RegistrationId::fromString(str_repeat('é', 64))->value());
    }

    /**
     * @return iterable<string, array{string, InvalidValue}>
     */
    public static function provideInvalidRegistrationIds(): iterable
    {
        yield 'empty' => ['', InvalidValue::empty('registration_id')];
        yield 'blank' => [' ', InvalidValue::empty('registration_id')];
        yield 'too long' => [str_repeat('é', 65), InvalidValue::tooLong('registration_id', 64)];
    }

    #[DataProvider('provideInvalidRegistrationIds')]
    public function testInvalidRegistrationIds(string $value, InvalidValue $expected): void
    {
        $this->expectExceptionObject($expected);

        RegistrationId::fromString($value);
    }

    public function testRejectionCause(): void
    {
        $cause = RejectionCause::of('ERR_COMPTE_BLOQUE', 'Account blocked.');

        static::assertSame('ERR_COMPTE_BLOQUE', $cause->code());
        static::assertSame('Account blocked.', $cause->message());
        static::assertSame('', RejectionCause::of('ERREUR_TECHNIQUE', '')->message());

        $this->expectExceptionObject(InvalidValue::empty('rejection_cause_code'));

        RejectionCause::of(' ', 'No code.');
    }

    /**
     * @return iterable<string, array{string, PairingStatus, bool, RegistrationState}>
     */
    public static function providePairingStatuses(): iterable
    {
        yield 'valid' => ['APPAREILLAGE_VALIDE', PairingStatus::Validated, true, RegistrationState::Registered];
        yield 'in progress' => ['APPAREILLAGE_EC', PairingStatus::InProgress, false, RegistrationState::AwaitingPairing];
        yield 'not finalised' => ['APPAREILLAGE_NF', PairingStatus::NotFinalised, false, RegistrationState::AwaitingPairing];
        yield 'blocked' => ['PARTICULIER_BLOQUE', PairingStatus::CustomerBlocked, false, RegistrationState::Blocked];
        yield 'mandate expired' => ['MANDAT_ECHU', PairingStatus::MandateExpired, false, RegistrationState::MandateExpired];
    }

    #[DataProvider('providePairingStatuses')]
    public function testPairingStatuses(string $code, PairingStatus $status, bool $allowsTransmission, RegistrationState $state): void
    {
        static::assertSame($status, PairingStatus::fromCode($code));
        static::assertSame($code, $status->value);
        static::assertSame($allowsTransmission, $status->allowsTransmission());
        static::assertSame($state, $status->registrationState());
    }

    public function testUnknownPairingStatus(): void
    {
        static::assertNull(PairingStatus::fromCode('APPAREILLAGE_INCONNU'));
        static::assertNull(PairingStatus::fromCode('appareillage_valide'));
        static::assertCount(5, PairingStatus::cases());
    }
}
