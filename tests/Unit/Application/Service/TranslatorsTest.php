<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\SubmissionOutcomeApplier;
use TheBeardedBear\UrssafTiersPrestations\Application\Service\UrssafErrorTranslator;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Fake\Harness;

final class TranslatorsTest extends TestCase
{
    public function testTranslatesTheUrssafErrors(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_A', 'message a', 'description a'), ApiError::of('ERR_B', 'message b', 'description b'));

        $causes = UrssafErrorTranslator::rejectionCauses($errors);
        $submissionErrors = UrssafErrorTranslator::submissionErrors($errors);

        static::assertCount(2, $causes);
        static::assertSame(['ERR_A', 'message a'], [$causes[0]->code(), $causes[0]->message()]);
        static::assertSame(['ERR_B', 'message b'], [$causes[1]->code(), $causes[1]->message()]);
        static::assertCount(2, $submissionErrors);
        static::assertContainsOnlyInstancesOf(SubmissionError::class, $submissionErrors);
        static::assertSame(['ERR_A', 'message a', 'description a'], [$submissionErrors[0]->code(), $submissionErrors[0]->message(), $submissionErrors[0]->description()]);
        static::assertSame('ERR_B', $submissionErrors[1]->code());
    }

    public function testAppliesTheSubmissionOutcomes(): void
    {
        $harness = new Harness();
        $now = Harness::at('2022-03-01T01:00:00Z');
        $accepted = $harness->paymentRequest(1, SubmissionState::Submitting);
        $rejected = $harness->paymentRequest(2, SubmissionState::Submitting);
        $unconfirmed = $harness->paymentRequest(3, SubmissionState::Submitting);

        $acceptedItem = SubmissionOutcomeApplier::apply($accepted, SubmissionOutcome::accepted('INV-1', PaymentRequestId::fromString('u-1'), '10'), $now, 5);
        $rejectedItem = SubmissionOutcomeApplier::apply(
            $rejected,
            SubmissionOutcome::rejected('INV-2', 'ERR_CMPT_BLOQUE', ApiErrors::of(ApiError::of('ERR_CMPT_BLOQUE', 'm', 'd')), RejectionKind::CustomerBlocked),
            $now,
            5,
        );
        $unconfirmedItem = SubmissionOutcomeApplier::apply($unconfirmed, null, $now, 5);

        static::assertEquals(['pr-1', ItemOutcome::Accepted, ['10']], [$acceptedItem->subject, $acceptedItem->outcome, $acceptedItem->codes]);
        static::assertSame(SubmissionState::Submitted, $accepted->state());
        static::assertSame('u-1', $accepted->paymentRequestId()?->value());
        static::assertSame('10', $accepted->rawUrssafStatus());
        static::assertSame('2022-03-01T01:00:00Z', $accepted->timeline()->submittedAt()?->toIso8601());
        static::assertEquals(['pr-2', ItemOutcome::Rejected, ['ERR_CMPT_BLOQUE']], [$rejectedItem->subject, $rejectedItem->outcome, $rejectedItem->codes]);
        static::assertSame(SubmissionState::Rejected, $rejected->state());
        static::assertSame('ERR_CMPT_BLOQUE', $rejected->rawUrssafStatus());
        static::assertSame('ERR_CMPT_BLOQUE', $rejected->submissionErrors()[0]->code());
        static::assertSame('2022-03-01T01:00:00Z', $rejected->timeline()->updatedAt()->toIso8601());
        static::assertEquals(['pr-3', ItemOutcome::Unconfirmed, []], [$unconfirmedItem->subject, $unconfirmedItem->outcome, $unconfirmedItem->codes]);
        static::assertSame(SubmissionState::Submitting, $unconfirmed->state());
    }
}
