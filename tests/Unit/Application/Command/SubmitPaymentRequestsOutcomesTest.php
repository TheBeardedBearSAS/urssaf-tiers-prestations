<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingRecheckRequested;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Event\CustomerPairingStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RegistrationState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\RejectionCause;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestRejectedByUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

/**
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class SubmitPaymentRequestsOutcomesTest extends TestCase
{
    public function testMatchesTheOutcomesByInvoiceNumber(): void
    {
        $harness = SubmissionScenario::harness(3, new WorkflowPolicy());
        $harness->gateway->willSubmitWith(static fn(): array => [
            self::accepted('INV-3', 'U-3'),
            self::accepted('INV-9', 'U-9'),
            self::accepted('INV-1', 'U-1'),
        ]);

        $report = SubmissionScenario::submit($harness, 1, 2, 3);

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::Accepted, ['10']),
            new BatchItem('pr-2', ItemOutcome::Unconfirmed),
            new BatchItem('pr-3', ItemOutcome::Accepted, ['10']),
        ], $report->items());
        static::assertSame('U-1', $harness->paymentRequests->stored('pr-1')->paymentRequestId()?->value());
        static::assertSame('U-3', $harness->paymentRequests->stored('pr-3')->paymentRequestId()?->value());
        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 2));
    }

    public function testFallsBackToThePositionWhenTheInvoiceNumberIsNotEchoed(): void
    {
        $harness = SubmissionScenario::harness(2, new WorkflowPolicy());
        $harness->gateway->willSubmitWith(static fn(): array => [self::accepted('', 'U-1'), self::accepted('', 'U-2')]);

        SubmissionScenario::submit($harness, 1, 2);

        static::assertSame('U-1', $harness->paymentRequests->stored('pr-1')->paymentRequestId()?->value());
        static::assertSame('U-2', $harness->paymentRequests->stored('pr-2')->paymentRequestId()?->value());
    }

    public function testDoesNotFallBackToThePositionWhenTheCountDiffers(): void
    {
        $harness = SubmissionScenario::harness(2, new WorkflowPolicy());
        $harness->gateway->willSubmitWith(static fn(): array => [self::accepted('', 'U-1')]);

        $report = SubmissionScenario::submit($harness, 1, 2);

        static::assertSame(2, $report->count(ItemOutcome::Unconfirmed));
    }

    public function testDoesNotUseAnOutcomeEchoingAnotherInvoiceNumberAtThePosition(): void
    {
        $harness = SubmissionScenario::harness(2, new WorkflowPolicy());
        $harness->gateway->willSubmitWith(static fn(): array => [self::accepted('INV-2', 'U-2'), self::accepted('INV-7', 'U-7')]);

        $report = SubmissionScenario::submit($harness, 1, 2);

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::Unconfirmed),
            new BatchItem('pr-2', ItemOutcome::Accepted, ['10']),
        ], $report->items());
    }

    public function testAnInvoiceNumberEchoedTwiceIsUnconfirmed(): void
    {
        $harness = SubmissionScenario::harness(2, new WorkflowPolicy());
        $harness->gateway->willSubmitWith(static fn(): array => [self::accepted('INV-1', 'U-1'), self::accepted('INV-1', 'U-2')]);

        $report = SubmissionScenario::submit($harness, 1, 2);

        static::assertSame(2, $report->count(ItemOutcome::Unconfirmed));
    }

    public function testKeepsTheWarningsOfAnIntegratedRequest(): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $outcome = SubmissionOutcome::acceptedWithWarnings(
            'INV-1',
            PaymentRequestId::fromString('U-1'),
            '10',
            ApiErrors::of(ApiError::of('W1', 'Warning.', '')),
        );
        $harness->gateway->willSubmitWith(static fn(): array => [$outcome]);

        $report = SubmissionScenario::submit($harness, 1);

        static::assertEquals([new BatchItem('pr-1', ItemOutcome::Accepted, ['10', 'W1'])], $report->items());
        static::assertSame(SubmissionState::Submitted, SubmissionScenario::state($harness, 1));
    }

    public function testLeavesADuplicateInvoiceNumberSubmittingForReconciliation(): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $harness->gateway->willSubmitWith(static fn(): array => [self::rejected('INV-1', 'ERR_FACTURE_DOUBLON', RejectionKind::Duplicate)]);

        $report = SubmissionScenario::submit($harness, 1);

        static::assertEquals([new BatchItem('pr-1', ItemOutcome::Unconfirmed, ['ERR_FACTURE_DOUBLON'])], $report->items());
        static::assertSame(SubmissionState::Submitting, SubmissionScenario::state($harness, 1));
        static::assertSame([], $harness->events->names());
    }

    public function testRequeuesATechnicalRejection(): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $harness->gateway->willSubmitWith(static fn(): array => [self::rejected('INV-1', 'ERREUR_TECHNIQUE', RejectionKind::Retryable)]);

        $report = SubmissionScenario::submit($harness, 1);

        static::assertEquals([new BatchItem('pr-1', ItemOutcome::Requeued, ['ERREUR_TECHNIQUE'])], $report->items());
        $stored = $harness->paymentRequests->stored('pr-1');
        static::assertSame(SubmissionState::Pending, $stored->state());
        static::assertSame('ERREUR_TECHNIQUE', $stored->submissionErrors()[0]->code());
        static::assertSame(['PaymentRequestSubmissionFailed'], $harness->events->names());
    }

    public function testLeavesATechnicalRejectionInErrorOnceTheMaximumNumberOfAttemptsIsReached(): void
    {
        $policy = new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P90D'), new DateInterval('PT15M'), 'menage.fr', 10, 100, 1000, 1);
        $harness = SubmissionScenario::harness(1, $policy);
        $harness->gateway->willSubmitWith(static fn(): array => [self::rejected('INV-1', 'ERREUR_TECHNIQUE', RejectionKind::Retryable)]);

        $report = SubmissionScenario::submit($harness, 1);

        static::assertEquals([new BatchItem('pr-1', ItemOutcome::Exhausted, ['ERREUR_TECHNIQUE'])], $report->items());
        static::assertSame(SubmissionState::Error, SubmissionScenario::state($harness, 1));
    }

    /**
     * @return iterable<string, array{RejectionKind, RegistrationState, class-string}>
     */
    public static function provideRegistrationRefusals(): iterable
    {
        yield 'customer blocked' => [RejectionKind::CustomerBlocked, RegistrationState::Blocked, CustomerPairingStatusChanged::class];
        yield 'mandate expired' => [RejectionKind::MandateExpired, RegistrationState::MandateExpired, CustomerPairingStatusChanged::class];
        yield 'pairing missing' => [RejectionKind::PairingMissing, RegistrationState::AwaitingPairing, CustomerPairingRecheckRequested::class];
    }

    /**
     * @param class-string $event
     */
    #[DataProvider('provideRegistrationRefusals')]
    public function testARefusalAboutTheCustomerAlsoMovesItsRegistration(RejectionKind $kind, RegistrationState $state, string $event): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $harness->registration(1, RegistrationState::Registered);
        $harness->gateway->willSubmitWith(static fn(): array => [self::rejected('INV-1', 'ERR_X', $kind)]);

        $report = SubmissionScenario::submit($harness, 1);

        static::assertEquals([new BatchItem('pr-1', ItemOutcome::Rejected, ['ERR_X'])], $report->items());
        static::assertSame(SubmissionState::Rejected, SubmissionScenario::state($harness, 1));
        $registration = $harness->registrations->stored('reg-1');
        static::assertSame($state, $registration->status()->state());
        static::assertSame(SubmissionScenario::LATER, $registration->status()->updatedAt()->toIso8601());
        static::assertSame(['begin', 'save:reg-1', 'save:pr-1', 'commit'], \array_slice($harness->journal->entries(), -6, 4));
        $events = $harness->events->events();
        static::assertCount(2, $events);
        static::assertInstanceOf($event, $events[0]);
        static::assertInstanceOf(PaymentRequestRejectedByUrssaf::class, $events[1]);
    }

    #[DataProvider('provideRegistrationRefusals')]
    public function testARefusalAboutAnUnknownCustomerOnlyRejectsTheRequest(RejectionKind $kind): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $harness->gateway->willSubmitWith(static fn(): array => [self::rejected('INV-1', 'ERR_X', $kind)]);

        SubmissionScenario::submit($harness, 1);

        static::assertSame(SubmissionState::Rejected, SubmissionScenario::state($harness, 1));
        static::assertSame(['PaymentRequestRejectedByUrssaf'], $harness->events->names());
    }

    #[DataProvider('provideRegistrationRefusals')]
    public function testARefusalLeavesARegistrationWhosePairingIsNotFollowedUntouched(RejectionKind $kind): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $registration = $harness->registration(1, RegistrationState::Registered);
        $registration->markRejected([RejectionCause::of('ERR_RECHERCHE_PARTICULIER', 'Gone.')], Harness::at(Harness::NOW));
        $registration->releaseEvents();
        $harness->registrations->add($registration);
        $harness->gateway->willSubmitWith(static fn(): array => [self::rejected('INV-1', 'ERR_X', $kind)]);

        SubmissionScenario::submit($harness, 1);

        static::assertSame(RegistrationState::Rejected, $harness->registrations->stored('reg-1')->status()->state());
        static::assertNotContains('save:reg-1', $harness->journal->entries());
    }

    public function testAFinalRejectionLeavesTheRegistrationUntouched(): void
    {
        $harness = SubmissionScenario::harness(1, new WorkflowPolicy());
        $harness->registration(1, RegistrationState::Registered);
        $harness->gateway->willSubmitWith(static fn(): array => [self::rejected('INV-1', 'ERR_X', RejectionKind::Final)]);

        SubmissionScenario::submit($harness, 1);

        static::assertSame(RegistrationState::Registered, $harness->registrations->stored('reg-1')->status()->state());
        static::assertNotContains('save:reg-1', $harness->journal->entries());
    }

    private static function accepted(string $invoiceNumber, string $urssafId): SubmissionOutcome
    {
        return SubmissionOutcome::accepted($invoiceNumber, PaymentRequestId::fromString($urssafId), '10');
    }

    private static function rejected(string $invoiceNumber, string $code, RejectionKind $kind): SubmissionOutcome
    {
        return SubmissionOutcome::rejected($invoiceNumber, $code, ApiErrors::of(ApiError::of($code, 'Refused.', '')), $kind);
    }
}
