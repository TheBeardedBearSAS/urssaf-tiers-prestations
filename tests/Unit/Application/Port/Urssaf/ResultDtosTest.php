<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionKind;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\StatusInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SubmissionOutcome;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

final class ResultDtosTest extends TestCase
{
    public function testSnapshot(): void
    {
        $id = PaymentRequestId::fromString('aaaa1111-2222-3b3b-4d4-55e666ffff77');
        $status = StatusInfo::of('70', 'Payée');
        $rejection = RejectionInfo::of('CONTEST_AUTRE', "La prestation n'a pas été honorée.");
        $transfer = TransferInfo::of(Money::fromCents(200_000), CalendarDate::fromString('2019-12-05'));

        $snapshot = PaymentRequestSnapshot::of($id, '2022-AZ-00001', $status, $rejection, $transfer);

        static::assertSame($id, $snapshot->paymentRequestId());
        static::assertSame('2022-AZ-00001', $snapshot->invoiceNumber());
        static::assertSame($status, $snapshot->status());
        static::assertSame('70', $status->code());
        static::assertSame('Payée', $status->label());
        static::assertSame($rejection, $snapshot->rejection());
        static::assertSame('CONTEST_AUTRE', $rejection->code());
        static::assertSame("La prestation n'a pas été honorée.", $rejection->comment());
        static::assertSame($transfer, $snapshot->transfer());
        static::assertSame(200_000, $transfer->amount()->cents());
        static::assertSame('2019-12-05', $transfer->valueDate()->toString());

        $bare = PaymentRequestSnapshot::of($id, '', $status, null, null);
        static::assertNull($bare->rejection());
        static::assertNull($bare->transfer());
    }

    public function testAcceptedOutcome(): void
    {
        $id = PaymentRequestId::fromString('id-1');

        $outcome = SubmissionOutcome::accepted('2022-AZ-00001', $id, '10');

        static::assertTrue($outcome->isAccepted());
        static::assertSame('2022-AZ-00001', $outcome->invoiceNumber());
        static::assertSame('10', $outcome->rawStatus());
        static::assertSame($id, $outcome->paymentRequestId());
        static::assertTrue($outcome->errors()->isEmpty());
        static::assertNull($outcome->rejectionKind());
        static::assertTrue($outcome->concerns(InvoiceNumber::fromString('2022-AZ-00001')));
        static::assertFalse($outcome->concerns(InvoiceNumber::fromString('2022-AZ-00002')));
    }

    public function testAcceptedOutcomeWithWarnings(): void
    {
        $id = PaymentRequestId::fromString('id-1');
        $warnings = ApiErrors::of(ApiError::of('W', 'Warning.', ''));

        $outcome = SubmissionOutcome::acceptedWithWarnings('2022-AZ-00001', $id, '10', $warnings);

        static::assertTrue($outcome->isAccepted());
        static::assertSame('10', $outcome->rawStatus());
        static::assertSame($id, $outcome->paymentRequestId());
        static::assertSame($warnings, $outcome->errors());
        static::assertNull($outcome->rejectionKind());
    }

    public function testRejectedOutcome(): void
    {
        $errors = ApiErrors::of(ApiError::of('ERR_CMPT_BLOQUE', 'Compte bloqué.', 'Le compte du particulier est bloqué.'));

        $outcome = SubmissionOutcome::rejected('TEST-1', 'ERR_CMPT_BLOQUE', $errors, RejectionKind::CustomerBlocked);

        static::assertFalse($outcome->isAccepted());
        static::assertSame('TEST-1', $outcome->invoiceNumber());
        static::assertSame('ERR_CMPT_BLOQUE', $outcome->rawStatus());
        static::assertNull($outcome->paymentRequestId());
        static::assertSame($errors, $outcome->errors());
        static::assertSame(RejectionKind::CustomerBlocked, $outcome->rejectionKind());
    }

    public function testRejectedOutcomeRequiresErrors(): void
    {
        $this->expectExceptionObject(InvariantViolation::because(
            'submission_outcome.missing_errors',
            'A rejected submission outcome must carry at least one error.',
        ));

        SubmissionOutcome::rejected('TEST-1', '10', ApiErrors::none(), RejectionKind::Final);
    }
}
