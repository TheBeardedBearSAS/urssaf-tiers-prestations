<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;

final class RegistrationStateTest extends TestCase
{
    /**
     * Expected transition table, written independently from the implementation.
     */
    private const TABLE = [
        'pending' => ['submitting'],
        'submitting' => ['awaiting_pairing', 'rejected', 'error', 'invalid_credentials'],
        'awaiting_pairing' => ['awaiting_pairing', 'registered', 'blocked', 'mandate_expired', 'rejected'],
        'registered' => ['awaiting_pairing', 'registered', 'blocked', 'mandate_expired', 'rejected'],
        'blocked' => ['awaiting_pairing', 'registered', 'blocked', 'mandate_expired', 'rejected'],
        'mandate_expired' => ['awaiting_pairing', 'registered', 'blocked', 'mandate_expired', 'rejected'],
        'rejected' => [],
        'error' => ['pending'],
        'invalid_credentials' => ['pending'],
    ];

    public function testWireValues(): void
    {
        static::assertSame(
            ['pending', 'submitting', 'awaiting_pairing', 'registered', 'rejected', 'blocked', 'mandate_expired', 'error', 'invalid_credentials'],
            array_map(static fn(RegistrationState $state): string => $state->value, RegistrationState::cases()),
        );
    }

    /**
     * @return iterable<string, array{RegistrationState, RegistrationState, bool}>
     */
    public static function provideTransitions(): iterable
    {
        foreach (RegistrationState::cases() as $from) {
            foreach (RegistrationState::cases() as $to) {
                yield $from->value . ' -> ' . $to->value => [$from, $to, \in_array($to->value, self::TABLE[$from->value], true)];
            }
        }
    }

    #[DataProvider('provideTransitions')]
    public function testTransitionMatrix(RegistrationState $from, RegistrationState $to, bool $allowed): void
    {
        static::assertSame($allowed, $from->canTransitionTo($to));
    }

    #[DataProvider('provideStates')]
    public function testAllowedTransitions(RegistrationState $state): void
    {
        $values = array_map(static fn(RegistrationState $target): string => $target->value, $state->allowedTransitions());
        sort($values);
        $expected = self::TABLE[$state->value];
        sort($expected);

        static::assertSame($expected, $values);
        static::assertSame($expected === [], $state->isFinal());
    }

    /**
     * @return iterable<string, array{RegistrationState}>
     */
    public static function provideStates(): iterable
    {
        foreach (RegistrationState::cases() as $state) {
            yield $state->value => [$state];
        }
    }

    #[DataProvider('provideStates')]
    public function testFollowsPairingStatus(RegistrationState $state): void
    {
        static::assertSame(
            \in_array($state->value, ['awaiting_pairing', 'registered', 'blocked', 'mandate_expired'], true),
            $state->followsPairingStatus(),
        );
    }

    #[DataProvider('provideStates')]
    public function testHasPairingToFollow(RegistrationState $state): void
    {
        static::assertSame(
            \in_array($state->value, ['awaiting_pairing', 'blocked', 'mandate_expired'], true),
            $state->hasPairingToFollow(),
        );
    }
}
