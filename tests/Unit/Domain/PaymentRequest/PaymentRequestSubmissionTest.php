<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestRejectedByUrssaf;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Event\PaymentRequestSubmitted;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

final class PaymentRequestSubmissionTest extends TestCase
{
    private const LATER = '2022-03-02T00:00:00Z';

    public function testMarkAccepted(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitting);
        $urssafId = PaymentRequestId::fromString('urssaf-9');
        $request->markAccepted($urssafId, PaymentRequestStatus::Integrated, Fixtures::at(self::LATER));

        static::assertSame($urssafId, $request->paymentRequestId());
        static::assertSame(PaymentRequestStatus::Integrated, $request->urssafStatus());
        static::assertSame('10', $request->rawUrssafStatus());
        static::assertSame(self::LATER, $request->lastStatusChangeAt()?->toIso8601());
        static::assertSame(self::LATER, $request->timeline()->submittedAt()?->toIso8601());
        static::assertNull($request->lastPolledAt());

        $events = $request->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(PaymentRequestSubmitted::class, $event);
        static::assertSame($request->id(), $event->paymentRequestId());
        static::assertSame($request->tenantId(), $event->tenantId());
        static::assertSame($urssafId, $event->urssafPaymentRequestId());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }

    public function testEventsAreReleasedTogether(): void
    {
        $request = Fixtures::pendingRequest();
        $request->startSubmission(Fixtures::at(self::LATER));
        $request->markAccepted(PaymentRequestId::fromString('urssaf-9'), '10', Fixtures::at(self::LATER));
        $events = $request->releaseEvents();

        static::assertCount(2, $events);
        static::assertInstanceOf(PaymentRequestSubmitted::class, $events[1]);
        static::assertSame([], $request->releaseEvents());
    }

    public function testMarkAcceptedWithABlankStatus(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitting);
        $request->markAccepted(PaymentRequestId::fromString('urssaf-9'), '', Fixtures::at(self::LATER));

        static::assertSame(SubmissionState::Submitted, $request->state());
        static::assertNull($request->rawUrssafStatus());
        static::assertSame('urssaf-9', $request->paymentRequestId()?->value());
    }

    public function testReconcileAfterAFailureClearsTheErrors(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitting);
        $request->markFailed([SubmissionError::of('ERREUR_TECHNIQUE', 'm', 'd')], Fixtures::at(Fixtures::NOW));
        $request->reconcileAsSubmitted(PaymentRequestId::fromString('urssaf-9'), '30', Fixtures::at(self::LATER));

        static::assertSame([], $request->submissionErrors());
        static::assertSame(PaymentRequestStatus::Validated, $request->urssafStatus());
        static::assertSame(self::LATER, $request->timeline()->submittedAt()?->toIso8601());
        $events = $request->releaseEvents();
        static::assertCount(2, $events);
        static::assertInstanceOf(PaymentRequestSubmitted::class, $events[1]);
    }

    public function testMarkRejected(): void
    {
        $request = Fixtures::requestIn(SubmissionState::Submitting);
        $errors = [SubmissionError::of('ERR_CMPT_BLOQUE', 'Compte bloqué.', 'Le compte du particulier est bloqué.')];
        $request->markRejected($errors, 'ERR_CMPT_BLOQUE', Fixtures::at(self::LATER));

        static::assertSame($errors, $request->submissionErrors());
        static::assertSame('ERR_CMPT_BLOQUE', $request->rawUrssafStatus());
        static::assertNull($request->urssafStatus());
        static::assertNull($request->paymentRequestId());

        $events = $request->releaseEvents();
        static::assertCount(1, $events);
        $event = $events[0];
        static::assertInstanceOf(PaymentRequestRejectedByUrssaf::class, $event);
        static::assertSame($request->id(), $event->paymentRequestId());
        static::assertSame($request->tenantId(), $event->tenantId());
        static::assertSame($errors, $event->errors());
        static::assertSame(self::LATER, $event->occurredAt()->format('Y-m-d\TH:i:s\Z'));
    }
}
