<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestSubmissionFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\Customer\CustomerFixtures;

/**
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class PaymentRequestTransitionsTest extends TestCase
{
    private const LATER = '2022-03-02T00:00:00Z';

    /** Expected resulting state per operation and initial state; null = refused. */
    private const MATRIX = [
        'startSubmission' => ['pending' => 'submitting'],
        'markAccepted' => ['submitting' => 'submitted'],
        'reconcileAsSubmitted' => ['submitting' => 'submitted', 'error' => 'submitted'],
        'markRejected' => ['submitting' => 'rejected'],
        'markFailed' => ['submitting' => 'error'],
        'markInvalidCredentials' => ['submitting' => 'invalid_credentials'],
        'retry' => ['error' => 'pending', 'invalid_credentials' => 'pending'],
        'applyUrssafStatus' => ['submitted' => 'submitted'],
    ];

    /**
     * @return iterable<string, array{string, SubmissionState, SubmissionState|null}>
     */
    public static function provideMatrix(): iterable
    {
        foreach (self::MATRIX as $operation => $allowed) {
            foreach (SubmissionState::cases() as $from) {
                $to = $allowed[$from->value] ?? null;
                yield $operation . ' from ' . $from->value => [$operation, $from, $to === null ? null : SubmissionState::from($to)];
            }
        }
    }

    #[DataProvider('provideMatrix')]
    public function testTransitionMatrix(string $operation, SubmissionState $from, ?SubmissionState $expected): void
    {
        $request = Fixtures::requestIn($from);
        if (!$expected instanceof SubmissionState) {
            $this->expectException(InvalidStateTransition::class);
        }

        self::apply($request, $operation);

        static::assertSame($expected, $request->state());
        static::assertSame(self::LATER, $request->timeline()->updatedAt()->toIso8601());
    }

    public function testStartSubmissionRecordsTheStartInstant(): void
    {
        $request = Fixtures::pendingRequest();
        $request->startSubmission(Fixtures::at(self::LATER));

        static::assertSame(self::LATER, $request->timeline()->submissionStartedAt()?->toIso8601());
        static::assertSame([], $request->tracking()->history());
    }

    public function testFailureKeepsTheErrorsUntilRetry(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitting);
        $errors = [SubmissionError::of('ERREUR_TECHNIQUE', 'Erreur technique', 'Une erreur')];
        $request->markFailed($errors, Fixtures::at(self::LATER));

        static::assertSame($errors, $request->submissionErrors());
        $events = $request->releaseEvents();
        static::assertCount(1, $events);
        $failed = $events[0];
        static::assertInstanceOf(PaymentRequestSubmissionFailed::class, $failed);
        static::assertSame('local-1', $failed->paymentRequestId()->value());
        static::assertSame('tenant-1', $failed->tenantId()->value());
        static::assertSame($errors, $failed->errors());
        static::assertSame(self::LATER, Instant::fromDateTime($failed->occurredAt())->toIso8601());

        $request->retry(Fixtures::at(self::LATER));
        static::assertSame(SubmissionState::Pending, $request->state());
        static::assertSame($errors, $request->submissionErrors());
        static::assertSame([], $request->releaseEvents());
    }

    public function testEachSubmissionStartCountsAnAttempt(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Pending);
        static::assertSame(0, $request->attempts());
        static::assertNull($request->lastAttemptAt());

        $request->startSubmission(Fixtures::at(Fixtures::NOW));
        $request->markFailed([], Fixtures::at(Fixtures::NOW));
        $request->retry(Fixtures::at(Fixtures::NOW));
        $request->startSubmission(Fixtures::at(self::LATER));

        static::assertSame(2, $request->attempts());
        static::assertSame(self::LATER, $request->lastAttemptAt()?->toIso8601());
    }

    public function testAFailureIsNotRetriedOnceTheMaximumNumberOfAttemptsIsReached(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Pending);
        $now = Fixtures::at(self::LATER);
        foreach ([1, 2] as $attempt) {
            $request->startSubmission($now);
            $request->markFailed([], $now);
            static::assertSame($attempt < 2, $request->canRetry(2), (string) $attempt);
            if ($attempt < 2) {
                $request->retry($now, 2);
            }
        }

        $exception = CustomerFixtures::assertViolationCode('payment_request.max_attempts_reached', static function () use ($request, $now): void {
            $request->retry($now, 2);
        });

        static::assertSame(['attempts' => 2, 'max_attempts' => 2], $exception->params());
        static::assertSame('The payment request failed 2 times: it is not retried automatically any more.', $exception->getMessage());
        static::assertSame(SubmissionState::Error, $request->state());
        static::assertTrue($request->canRetry(3));
    }

    public function testTheDefaultMaximumIsFiveAttempts(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Pending);
        $now = Fixtures::at(self::LATER);
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $request->startSubmission($now);
            $request->markFailed([], $now);
            if ($attempt < 5) {
                $request->retry($now);
            }
        }

        static::assertFalse($request->canRetry());
        $this->expectException(InvariantViolation::class);
        $request->retry($now);
    }

    public function testRefusedCredentialsAreAlwaysRetried(): void
    {
        $request = Fixtures::requestIn(SubmissionState::InvalidCredentials);

        static::assertTrue($request->canRetry(1));
        $request->retry(Fixtures::at(self::LATER), 1);

        static::assertSame(SubmissionState::Pending, $request->state());
    }

    public function testCanRetryDoesNotCheckTheTransition(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitted);

        static::assertTrue($request->canRetry(1));
        $this->expectException(InvalidStateTransition::class);
        $request->retry(Fixtures::at(self::LATER), 1);
    }

    public function testInvalidCredentialsClearsTheErrors(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitting);
        $request->markInvalidCredentials(Fixtures::at(self::LATER));

        static::assertSame([], $request->submissionErrors());
        static::assertNull($request->paymentRequestId());
    }

    private static function apply(PaymentRequest $request, string $operation): void
    {
        $at = Fixtures::at(self::LATER);
        $id = PaymentRequestId::fromString('urssaf-2');
        $errors = [SubmissionError::of('ERR_CMPT_BLOQUE', 'm', 'd')];
        match ($operation) {
            'startSubmission' => $request->startSubmission($at),
            'markAccepted' => $request->markAccepted($id, '10', $at),
            'reconcileAsSubmitted' => $request->reconcileAsSubmitted($id, '10', $at),
            'markRejected' => $request->markRejected($errors, 'ERR_CMPT_BLOQUE', $at),
            'markFailed' => $request->markFailed($errors, $at),
            'markInvalidCredentials' => $request->markInvalidCredentials($at),
            'retry' => $request->retry($at),
            default => $request->applyUrssafStatus('20', null, null, $at),
        };
    }
}
