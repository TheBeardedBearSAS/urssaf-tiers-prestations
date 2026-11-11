<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use Closure;
use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\Assert;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequests;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\SubmitPaymentRequestsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LocalPaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;
use Throwable;

/**
 * Shared scenario of the payment request submission tests.
 */
final class SubmissionScenario
{
    public const LATER = '2022-03-01T00:05:00Z';

    public static function harness(int $count, WorkflowPolicy $policy): Harness
    {
        $harness = new Harness($policy);
        foreach (range(1, $count) as $number) {
            $harness->paymentRequest($number, SubmissionState::Pending);
        }
        $harness->clock->moveTo(self::LATER);

        return $harness;
    }

    public static function policy(int $batchSize): WorkflowPolicy
    {
        return new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P90D'), new DateInterval('PT15M'), 'menage.fr', $batchSize);
    }

    public static function acceptAll(Harness $harness): void
    {
        $accepted = self::acceptedOutcomes();
        $harness->gateway->willSubmitWith(static fn(array $requests): array => array_map($accepted, $requests));
    }

    public static function failOnSecondCall(Harness $harness, Throwable $failure): void
    {
        $accepted = self::acceptedOutcomes();
        $calls = 0;
        $harness->gateway->willSubmitWith(static function (array $requests) use (&$calls, $failure, $accepted): array|Throwable {
            ++$calls;

            return $calls === 2 ? $failure : array_map($accepted, $requests);
        });
    }

    /**
     * @return Closure(PaymentRequest): SubmissionOutcome accepted outcome (URSSAF id "U-<invoice number>", status 10)
     */
    public static function acceptedOutcomes(): Closure
    {
        $outcomes = [];
        foreach (range(1, 30) as $number) {
            $outcomes['INV-' . $number] = SubmissionOutcome::accepted('INV-' . $number, PaymentRequestId::fromString('U-INV-' . $number), '10');
        }

        return static fn(PaymentRequest $request): SubmissionOutcome => $outcomes[$request->invoiceNumber()->value()];
    }

    public static function retryableFailure(Harness $harness): RetryableUrssafFailure
    {
        try {
            self::submit($harness, ...range(1, \count($harness->paymentRequests->findByState(Harness::tenant(), SubmissionState::Pending, 100))));
        } catch (RetryableUrssafFailure $failure) {
            return $failure;
        }
        Assert::fail('Expected a retryable failure.');
    }

    public static function state(Harness $harness, int $number): SubmissionState
    {
        return $harness->paymentRequests->stored('pr-' . $number)->state();
    }

    public static function handler(Harness $harness): SubmitPaymentRequestsHandler
    {
        return new SubmitPaymentRequestsHandler($harness->paymentRequests, $harness->gateway, $harness->clock, $harness->unitOfWork, $harness->policy, $harness->registrations);
    }

    public static function submit(Harness $harness, int ...$numbers): BatchReport
    {
        return self::handler($harness)(SubmitPaymentRequests::of(
            Harness::tenant(),
            ...array_map(static fn(int $number): LocalPaymentRequestId => LocalPaymentRequestId::fromString('pr-' . $number), $numbers),
        ));
    }
}
