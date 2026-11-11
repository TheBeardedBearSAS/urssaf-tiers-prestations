<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest;

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafStatusChange;

final class UrssafStatusChangeTest extends TestCase
{
    public function testObservedFromStatus(): void
    {
        $change = UrssafStatusChange::observed(PaymentRequestStatus::Paid, Fixtures::at(Fixtures::NOW));

        static::assertSame('70', $change->rawCode());
        static::assertSame(PaymentRequestStatus::Paid, $change->status());
        static::assertSame(Fixtures::NOW, $change->observedAt()->toIso8601());
    }

    public function testObservedFromRawCode(): void
    {
        static::assertSame(PaymentRequestStatus::Integrated, UrssafStatusChange::observed('10', Fixtures::at(Fixtures::NOW))->status());

        $unknown = UrssafStatusChange::observed('ERR_CMPT_BLOQUE', Fixtures::at(Fixtures::NOW));
        static::assertSame('ERR_CMPT_BLOQUE', $unknown->rawCode());
        static::assertNull($unknown->status());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBlankCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [' '];
    }

    #[DataProvider('provideBlankCodes')]
    public function testBlankCodeIsRejected(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::empty('urssaf_status'));

        UrssafStatusChange::observed($code, Fixtures::at(Fixtures::NOW));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideFollowUp(): iterable
    {
        yield 'unknown code, before its maximum age' => ['ERR_X', '2022-03-10T23:59:59Z', true];
        yield 'unknown code, at its maximum age' => ['ERR_X', '2022-03-11T00:00:00Z', false];
        yield 'non final' => ['30', '2030-01-01T00:00:00Z', true];
        yield 'final' => ['40', '2022-03-01T00:00:00Z', false];
        yield 'final regularised' => ['270', '2022-03-01T00:00:00Z', false];
        yield 'paid, one second before the deadline' => ['70', '2022-03-30T23:59:59Z', true];
        yield 'paid, at the deadline' => ['70', '2022-03-31T00:00:00Z', false];
        yield 'paid, after the deadline' => ['70', '2022-03-31T00:00:01Z', false];
        yield 'recovered, before the deadline' => ['120', '2022-03-30T23:59:59Z', true];
        yield 'recovered, at the deadline' => ['120', '2022-03-31T00:00:00Z', false];
    }

    #[DataProvider('provideFollowUp')]
    public function testIsStillFollowed(string $code, string $now, bool $expected): void
    {
        $change = UrssafStatusChange::observed($code, Fixtures::at(Fixtures::NOW));

        static::assertSame($expected, $change->isStillFollowed(Fixtures::at($now), new PollingWindow(new DateInterval('P30D'), new DateInterval('P10D'), new DateInterval('PT0S'))));
    }
}
