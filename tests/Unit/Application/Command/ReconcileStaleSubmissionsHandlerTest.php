<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Command;

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleSubmissions;
use TheBeardedBear\UrssafTiersPrestations\Application\Command\ReconcileStaleSubmissionsHandler;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\StatusInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;
use Throwable;

final class ReconcileStaleSubmissionsHandlerTest extends TestCase
{
    private const LATER = '2022-03-01T00:20:00Z';

    public function testReconcilesTheFoundRequestsAndRequeuesTheOthers(): void
    {
        $harness = self::harness(2);
        $harness->gateway->willFindWith(static fn(): array => [self::snapshot('INV-1')]);

        $report = self::reconcile($harness, null);

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::Reconciled, ['10']),
            new BatchItem('pr-2', ItemOutcome::Requeued, ['urssaf.not_found']),
        ], $report->items());
        static::assertSame([['2022-03-01T00:05:00Z', 100]], $harness->paymentRequests->staleQueries());
        static::assertSame([['INV-1', 'INV-2']], self::searchedNumbers($harness));
        $reconciled = $harness->paymentRequests->stored('pr-1');
        static::assertSame(SubmissionState::Submitted, $reconciled->state());
        static::assertSame('U-INV-1', $reconciled->paymentRequestId()?->value());
        static::assertSame('10', $reconciled->rawUrssafStatus());
        static::assertSame(self::LATER, $reconciled->timeline()->submittedAt()?->toIso8601());
        $requeued = $harness->paymentRequests->stored('pr-2');
        static::assertSame(SubmissionState::Pending, $requeued->state());
        static::assertSame(self::LATER, $requeued->timeline()->updatedAt()->toIso8601());
        static::assertSame([ReconcileStaleSubmissionsHandler::NOT_FOUND], array_map(static fn(SubmissionError $error): string => $error->code(), $requeued->submissionErrors()));
        static::assertSame(
            'The submission was not confirmed and the invoice number is unknown to URSSAF.',
            $requeued->submissionErrors()[0]->message(),
        );
        static::assertSame('', $requeued->submissionErrors()[0]->description());
        static::assertSame(
            ['search', 'begin', 'save:pr-1', 'save:pr-2', 'commit', 'publish:PaymentRequestSubmitted', 'publish:PaymentRequestSubmissionFailed'],
            $harness->journal->entries(),
        );
    }

    public function testSearchesPerTenantByChunksOfTen(): void
    {
        $harness = self::harness(12);
        $harness->paymentRequest(13, SubmissionState::Submitting, Harness::OTHER_TENANT);
        $harness->paymentRequest(14, SubmissionState::Submitting);
        $harness->paymentRequest(15, SubmissionState::Pending);
        $harness->gateway->willFindWith(static fn(): array => []);

        $report = self::reconcile($harness, null);

        static::assertSame([10, 3, 1], array_map(\count(...), self::searchedNumbers($harness)));
        static::assertSame(['INV-11', 'INV-12', 'INV-14'], self::searchedNumbers($harness)[1]);
        static::assertSame(['INV-13'], self::searchedNumbers($harness)[2]);
        static::assertSame(['tenant-1', 'tenant-1', 'tenant-2'], $harness->gateway->tenants());
        static::assertSame(14, $report->count(ItemOutcome::Requeued));
        $limited = self::harness(4);
        $limited->gateway->willFindWith(static fn(): array => []);
        static::assertSame(3, self::reconcile($limited, 3)->total());
    }

    public function testIgnoresRecentSubmissions(): void
    {
        $harness = self::harness(1);
        $harness->clock->moveTo('2022-03-01T00:14:59Z');

        static::assertSame(0, self::reconcile($harness, null)->total());
    }

    public function testLeavesTheRequestsSubmittingWhenTheSearchIsRefused(): void
    {
        $harness = self::harness(1);
        $harness->paymentRequest(2, SubmissionState::Submitting, Harness::OTHER_TENANT);
        $harness->paymentRequest(3, SubmissionState::Submitting, 'tenant-3');
        $harness->gateway->willFindWith(static fn(PaymentRequestSearch $search): array|Throwable => match ($search->invoiceNumbers()[0]->value()) {
            'INV-1' => UrssafRequestRejected::withErrors(Operation::SearchPaymentRequests, 400, ApiErrors::of(ApiError::of('PARAM_INVALIDE', 'm', 'd'))),
            'INV-2' => UrssafAuthenticationFailed::withStatus(401),
            default => [self::snapshot('INV-3')],
        });

        $report = self::reconcile($harness, null);

        static::assertEquals([
            new BatchItem('pr-1', ItemOutcome::Failed, ['PARAM_INVALIDE']),
            new BatchItem('pr-2', ItemOutcome::Failed, ['urssaf.authentication_failed']),
            new BatchItem('pr-3', ItemOutcome::Reconciled, ['10']),
        ], $report->items());
        static::assertSame(SubmissionState::Submitting, $harness->paymentRequests->stored('pr-1')->state());
        static::assertSame(SubmissionState::Submitting, $harness->paymentRequests->stored('pr-2')->state());
    }

    public function testPropagatesTemporaryFailures(): void
    {
        $harness = self::harness(1);
        $failure = UrssafUnavailable::withStatus(Operation::SearchPaymentRequests, 503, null);
        $harness->gateway->willFindWith(static fn(): Throwable => $failure);

        $this->expectExceptionObject($failure);

        self::reconcile($harness, null);
    }

    private static function harness(int $count): Harness
    {
        $harness = new Harness();
        foreach (range(1, $count) as $number) {
            $harness->paymentRequest($number, SubmissionState::Submitting);
        }
        $harness->clock->moveTo(self::LATER);

        return $harness;
    }

    private static function snapshot(string $invoiceNumber): PaymentRequestSnapshot
    {
        return PaymentRequestSnapshot::of(PaymentRequestId::fromString('U-' . $invoiceNumber), $invoiceNumber, StatusInfo::of('10', 'label'), null, null);
    }

    /**
     * @return list<list<string>>
     */
    private static function searchedNumbers(Harness $harness): array
    {
        return array_map(
            static fn(PaymentRequestSearch $search): array => array_map(static fn(InvoiceNumber $number): string => $number->value(), $search->invoiceNumbers()),
            $harness->gateway->searches(),
        );
    }

    private static function reconcile(Harness $harness, ?int $limit): BatchReport
    {
        $handler = new ReconcileStaleSubmissionsHandler($harness->paymentRequests, $harness->gateway, $harness->clock, $harness->unitOfWork, $harness->policy);

        return $handler(new ReconcileStaleSubmissions($limit));
    }

    public function testLeavesANotFoundRequestInErrorOnceTheMaximumNumberOfAttemptsIsReached(): void
    {
        $harness = new Harness(new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('P90D'), new DateInterval('PT15M'), 'menage.fr', 10, 100, 1000, 1));
        $harness->paymentRequest(1, SubmissionState::Submitting);
        $harness->clock->moveTo(self::LATER);
        $harness->gateway->willFindWith(static fn(): array => []);

        $report = self::reconcile($harness, null);

        static::assertEquals([new BatchItem('pr-1', ItemOutcome::Exhausted, ['urssaf.not_found'])], $report->items());
        static::assertSame(SubmissionState::Error, $harness->paymentRequests->stored('pr-1')->state());
    }
}
