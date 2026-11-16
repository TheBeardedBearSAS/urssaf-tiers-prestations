<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Rejection;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\RejectionReason;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionError;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

final class UrssafValueObjectsTest extends TestCase
{
    public function testTransferInfo(): void
    {
        $transfer = TransferInfo::of(Money::fromCents(200_000), CalendarDate::fromString('2019-12-05'));

        static::assertSame(200_000, $transfer->amount()->cents());
        static::assertSame('2019-12-05', $transfer->valueDate()->toString());
    }

    public function testKnownRejection(): void
    {
        $rejection = Rejection::of('CONTEST_AUTRE', 'Not honoured.');

        static::assertSame('CONTEST_AUTRE', $rejection->code());
        static::assertSame('Not honoured.', $rejection->comment());
        static::assertSame(RejectionReason::ContestationOther, $rejection->reason());
    }

    public function testUnknownRejectionKeepsTheRawCode(): void
    {
        $rejection = Rejection::of('NEW_CODE', '');

        static::assertSame('NEW_CODE', $rejection->code());
        static::assertSame('', $rejection->comment());
        static::assertNull($rejection->reason());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBlankCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [" \t"];
    }

    #[DataProvider('provideBlankCodes')]
    public function testBlankRejectionCodeIsRejected(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::empty('rejection_code'));

        Rejection::of($code, 'comment');
    }

    public function testSubmissionError(): void
    {
        $error = SubmissionError::of('ERR_FACTURE_DOUBLON', 'Message', 'Description');

        static::assertSame('ERR_FACTURE_DOUBLON', $error->code());
        static::assertSame('Message', $error->message());
        static::assertSame('Description', $error->description());
    }

    #[DataProvider('provideBlankCodes')]
    public function testBlankSubmissionErrorCodeIsRejected(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::empty('submission_error_code'));

        SubmissionError::of($code, 'Message', 'Description');
    }
}
