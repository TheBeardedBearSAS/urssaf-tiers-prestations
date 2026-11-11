<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;

final class SubmissionStateTest extends TestCase
{
    private const ALLOWED = [
        'pending' => ['submitting'],
        'submitting' => ['submitted', 'rejected', 'error', 'invalid_credentials'],
        'submitted' => [],
        'rejected' => [],
        'error' => ['pending'],
        'invalid_credentials' => ['pending'],
    ];

    /**
     * @return iterable<string, array{SubmissionState, SubmissionState, bool}>
     */
    public static function provideMatrix(): iterable
    {
        foreach (SubmissionState::cases() as $from) {
            foreach (SubmissionState::cases() as $to) {
                yield $from->value . ' -> ' . $to->value => [$from, $to, \in_array($to->value, self::ALLOWED[$from->value], true)];
            }
        }
    }

    #[DataProvider('provideMatrix')]
    public function testCanTransitionTo(SubmissionState $from, SubmissionState $to, bool $allowed): void
    {
        static::assertSame($allowed, $from->canTransitionTo($to));
    }

    /**
     * @return iterable<string, array{SubmissionState, SubmissionState}>
     */
    public static function provideRefusedTransitions(): iterable
    {
        foreach (self::provideMatrix() as $name => [$from, $to, $allowed]) {
            if ($allowed) {
                continue;
            }
            yield $name => [$from, $to];
        }
    }

    #[DataProvider('provideRefusedTransitions')]
    public function testRefusedTransitionThrows(SubmissionState $from, SubmissionState $to): void
    {
        $this->expectExceptionObject(InvalidStateTransition::between('payment_request', $from->value, $to->value));

        $from->transitionTo($to);
    }

    public function testAllowedTransitionReturnsTheTarget(): void
    {
        foreach (self::ALLOWED as $from => $targets) {
            foreach ($targets as $to) {
                static::assertSame(SubmissionState::from($to), SubmissionState::from($from)->transitionTo(SubmissionState::from($to)));
            }
        }
    }

    public function testAllowedTransitions(): void
    {
        static::assertSame([SubmissionState::Submitting], SubmissionState::Pending->allowedTransitions());
        static::assertSame(
            [SubmissionState::Submitted, SubmissionState::Rejected, SubmissionState::Error, SubmissionState::InvalidCredentials],
            SubmissionState::Submitting->allowedTransitions(),
        );
        static::assertSame([], SubmissionState::Submitted->allowedTransitions());
        static::assertSame([], SubmissionState::Rejected->allowedTransitions());
        static::assertSame([SubmissionState::Pending], SubmissionState::Error->allowedTransitions());
        static::assertSame([SubmissionState::Pending], SubmissionState::InvalidCredentials->allowedTransitions());
    }

    public function testValues(): void
    {
        static::assertSame(
            ['pending', 'submitting', 'submitted', 'rejected', 'error', 'invalid_credentials'],
            array_map(static fn(SubmissionState $state): string => $state->value, SubmissionState::cases()),
        );
    }
}
