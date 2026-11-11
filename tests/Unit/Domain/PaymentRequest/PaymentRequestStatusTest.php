<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;

final class PaymentRequestStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{int, PaymentRequestStatus, bool, bool, string}>
     */
    public static function provideStatuses(): iterable
    {
        yield '10' => [10, PaymentRequestStatus::Integrated, false, false, 'Integrated'];
        yield '20' => [20, PaymentRequestStatus::AwaitingValidation, false, false, 'Awaiting customer validation'];
        yield '30' => [30, PaymentRequestStatus::Validated, false, false, 'Validated'];
        yield '40' => [40, PaymentRequestStatus::Refused, true, false, 'Refused by the customer'];
        yield '50' => [50, PaymentRequestStatus::Debited, false, false, 'Debited'];
        yield '60' => [60, PaymentRequestStatus::DebitRefused, false, false, 'Debit refused'];
        yield '70' => [70, PaymentRequestStatus::Paid, false, true, 'Paid'];
        yield '110' => [110, PaymentRequestStatus::Cancelled, true, false, 'Cancelled'];
        yield '111' => [111, PaymentRequestStatus::CancelledAfterUnpaid, true, false, 'Cancelled after unpaid'];
        yield '112' => [112, PaymentRequestStatus::CancelledAfterRecovery, true, false, 'Cancelled after recovery'];
        yield '113' => [113, PaymentRequestStatus::CancelledForSpecificReason, true, false, 'Cancelled for a specific reason'];
        yield '120' => [120, PaymentRequestStatus::Recovered, false, true, 'Recovered'];
        yield '260' => [260, PaymentRequestStatus::ProviderUnpaid, false, false, 'Provider unpaid'];
        yield '270' => [270, PaymentRequestStatus::ProviderRegularised, true, false, 'Regularised by the provider'];
    }

    #[DataProvider('provideStatuses')]
    public function testStatuses(int $code, PaymentRequestStatus $status, bool $final, bool $settled, string $label): void
    {
        static::assertSame($status, PaymentRequestStatus::fromCode($code));
        static::assertSame($status, PaymentRequestStatus::fromCode((string) $code));
        static::assertSame($final, $status->isFinal());
        static::assertSame($settled, $status->isSettled());
        static::assertSame($label, $status->label());
        static::assertCount(14, PaymentRequestStatus::cases());
    }

    /**
     * @return iterable<string, array{int|string}>
     */
    public static function provideUnknownCodes(): iterable
    {
        yield 'error code' => ['ERR_CMPT_BLOQUE'];
        yield 'empty' => [''];
        yield 'unknown string' => ['15'];
        yield 'unknown int' => [999];
        yield 'zero' => [0];
        yield 'decimal' => ['10.5'];
        yield 'numeric prefix' => ['10abc'];
    }

    #[DataProvider('provideUnknownCodes')]
    public function testUnknownCodesGiveNull(int|string $code): void
    {
        static::assertNull(PaymentRequestStatus::fromCode($code));
    }
}
