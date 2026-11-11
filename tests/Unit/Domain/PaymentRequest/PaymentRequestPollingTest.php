<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestStatusChanged;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Rejection;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

final class PaymentRequestPollingTest extends TestCase
{
    private const LATER = '2022-03-02T00:00:00Z';

    public function testStatusChange(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitted);
        $transfer = TransferInfo::of(Money::fromCents(1102), CalendarDate::fromString('2022-03-08'));
        $rejection = Rejection::of('REGUL_AUTRE', 'comment');
        $request->applyUrssafStatus(PaymentRequestStatus::Paid, $transfer, $rejection, Fixtures::at(self::LATER));

        static::assertSame(PaymentRequestStatus::Paid, $request->urssafStatus());
        static::assertSame($transfer, $request->tracking()->transfer());
        static::assertSame($rejection, $request->tracking()->rejection());
        static::assertSame(self::LATER, $request->lastPolledAt()?->toIso8601());
        static::assertSame(self::LATER, $request->lastStatusChangeAt()?->toIso8601());
        static::assertSame(self::LATER, $request->timeline()->updatedAt()->toIso8601());

        $events = $request->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(PaymentRequestStatusChanged::class, $event);
        static::assertSame($request->id(), $event->paymentRequestId());
        static::assertSame($request->tenantId(), $event->tenantId());
        static::assertSame('10', $event->previous()?->rawCode());
        static::assertSame('70', $event->current()->rawCode());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testFirstKnownStatus(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitting);
        $request->markAccepted(PaymentRequestId::fromString('urssaf-1'), '', Fixtures::at(Fixtures::NOW));
        $request->releaseEvents();
        $request->applyUrssafStatus('20', null, null, Fixtures::at(self::LATER));

        $events = $request->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(PaymentRequestStatusChanged::class, $event);
        static::assertNull($event->previous());
    }

    public function testUnchangedOrBlankStatusRecordsNoEvent(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitted);
        $request->applyUrssafStatus('10', null, null, Fixtures::at(self::LATER));
        $request->applyUrssafStatus('', null, null, Fixtures::at(self::LATER));

        static::assertSame([], $request->releaseEvents());
        static::assertSame(Fixtures::NOW, $request->lastStatusChangeAt()?->toIso8601());
        static::assertSame(self::LATER, $request->lastPolledAt()?->toIso8601());
    }

    public function testBlankFirstStatusRecordsNoEvent(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitting);
        $request->markAccepted(PaymentRequestId::fromString('urssaf-1'), '', Fixtures::at(Fixtures::NOW));
        $request->releaseEvents();
        $request->applyUrssafStatus(' ', null, null, Fixtures::at(self::LATER));

        static::assertSame([], $request->releaseEvents());
        static::assertNull($request->rawUrssafStatus());
    }

    /**
     * @return iterable<string, array{SubmissionState, string|null, string, bool}>
     */
    public static function providePollable(): iterable
    {
        yield 'pending' => [SubmissionState::Pending, null, self::LATER, false];
        yield 'submitting' => [SubmissionState::Submitting, null, self::LATER, false];
        yield 'rejected' => [SubmissionState::Rejected, null, self::LATER, false];
        yield 'error' => [SubmissionState::Error, null, self::LATER, false];
        yield 'submitted, integrated' => [SubmissionState::Submitted, null, self::LATER, true];
        yield 'submitted, unknown status' => [SubmissionState::Submitted, 'NEW', self::LATER, true];
        yield 'submitted, final' => [SubmissionState::Submitted, '40', self::LATER, false];
        yield 'paid, before the maximum age' => [SubmissionState::Submitted, '70', '2022-03-08T23:59:59Z', true];
        yield 'paid, at the maximum age' => [SubmissionState::Submitted, '70', '2022-03-09T00:00:00Z', false];
        yield 'unknown status, before its maximum age' => [SubmissionState::Submitted, 'NEW', '2022-03-04T23:59:59Z', true];
        yield 'unknown status, at its maximum age' => [SubmissionState::Submitted, 'NEW', '2022-03-05T00:00:00Z', false];
        yield 'non final, before the maximum polling age' => [SubmissionState::Submitted, '30', '2022-03-30T23:59:59Z', true];
        yield 'non final, at the maximum polling age' => [SubmissionState::Submitted, '30', '2022-03-31T00:00:00Z', false];
        yield 'integrated, at the maximum polling age' => [SubmissionState::Submitted, null, '2022-03-31T00:00:00Z', false];
    }

    #[DataProvider('providePollable')]
    public function testIsPollable(SubmissionState $state, ?string $status, string $now, bool $expected): void
    {
        $request = Fixtures::requestIn($state);
        if ($status !== null) {
            $request->applyUrssafStatus($status, null, null, Fixtures::at(self::LATER));
        }

        static::assertSame($expected, $request->isPollable(Fixtures::at($now), new PollingWindow(new DateInterval('P7D'), new DateInterval('P3D'), new DateInterval('P30D'))));
    }

    /**
     * @return iterable<string, array{SubmissionState, string, bool}>
     */
    public static function provideStale(): iterable
    {
        yield 'submitting, one second before' => [SubmissionState::Submitting, '2022-03-01T00:09:59Z', false];
        yield 'submitting, at the limit' => [SubmissionState::Submitting, '2022-03-01T00:10:00Z', true];
        yield 'pending' => [SubmissionState::Pending, '2022-04-01T00:00:00Z', false];
        yield 'submitted' => [SubmissionState::Submitted, '2022-04-01T00:00:00Z', false];
        yield 'error' => [SubmissionState::Error, '2022-04-01T00:00:00Z', false];
    }

    #[DataProvider('provideStale')]
    public function testIsStaleSubmission(SubmissionState $state, string $now, bool $expected): void
    {
        static::assertSame(
            $expected,
            Fixtures::requestIn($state)->isStaleSubmission(Fixtures::at($now), new DateInterval('PT10M')),
        );
    }
}
