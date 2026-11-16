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

use DateInterval;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Rejection;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafStatusChange;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafTracking;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

final class UrssafTrackingTest extends TestCase
{
    public function testNone(): void
    {
        $tracking = UrssafTracking::none();

        static::assertNull($tracking->paymentRequestId());
        static::assertNull($tracking->current());
        static::assertSame([], $tracking->history());
        static::assertNull($tracking->transfer());
        static::assertNull($tracking->rejection());
        static::assertNull($tracking->lastPolledAt());
        static::assertTrue($tracking->isStillFollowed(Fixtures::at(Fixtures::NOW), new PollingWindow(new DateInterval('PT0S'), new DateInterval('PT0S'), new DateInterval('PT0S'))));
    }

    public function testIdentifiedAs(): void
    {
        $tracking = $this->polled()->identifiedAs(PaymentRequestId::fromString('urssaf-1'), '20', Fixtures::at('2022-03-02T00:00:00Z'));

        static::assertSame('urssaf-1', $tracking->paymentRequestId()?->value());
        static::assertSame('20', $tracking->current()?->rawCode());
        static::assertCount(2, $tracking->history());
        static::assertSame(1000, $tracking->transfer()?->amount()->cents());
        static::assertSame('ANNUL_DBL', $tracking->rejection()?->code());
        static::assertSame(Fixtures::NOW, $tracking->lastPolledAt()?->toIso8601());
    }

    public function testObserveNewStatusAppendsToHistory(): void
    {
        $first = UrssafTracking::none()->observe(PaymentRequestStatus::Integrated, Fixtures::at(Fixtures::NOW));
        $second = $first->observe('30', Fixtures::at('2022-03-02T00:00:00Z'));

        static::assertSame('10', $first->current()?->rawCode());
        static::assertSame($second->current(), $second->history()[1]);
        static::assertSame(['10', '30'], array_map(static fn(UrssafStatusChange $change): string => $change->rawCode(), $second->history()));
        static::assertSame('2022-03-02T00:00:00Z', $second->history()[1]->observedAt()->toIso8601());
    }

    public function testObservingTheSameOrABlankStatusIsANoOp(): void
    {
        $tracking = $this->polled();

        static::assertSame($tracking, $tracking->observe('10', Fixtures::at('2022-03-05T00:00:00Z')));
        static::assertSame($tracking, $tracking->observe(PaymentRequestStatus::Integrated, Fixtures::at('2022-03-05T00:00:00Z')));
        static::assertSame($tracking, $tracking->observe('', Fixtures::at('2022-03-05T00:00:00Z')));
        static::assertSame($tracking, $tracking->observe('  ', Fixtures::at('2022-03-05T00:00:00Z')));
    }

    public function testObservePreservesTheOtherInformation(): void
    {
        $tracking = $this->polled()->observe('30', Fixtures::at('2022-03-05T00:00:00Z'));

        static::assertSame(1000, $tracking->transfer()?->amount()->cents());
        static::assertSame('ANNUL_DBL', $tracking->rejection()?->code());
        static::assertSame(Fixtures::NOW, $tracking->lastPolledAt()?->toIso8601());
    }

    public function testPolledReplacesTransferAndRejection(): void
    {
        $tracking = $this->polled()->polled('10', null, null, Fixtures::at('2022-03-05T00:00:00Z'));

        static::assertNull($tracking->transfer());
        static::assertNull($tracking->rejection());
        static::assertSame('2022-03-05T00:00:00Z', $tracking->lastPolledAt()?->toIso8601());
        static::assertCount(1, $tracking->history());
        static::assertSame(Fixtures::NOW, $tracking->current()?->observedAt()->toIso8601());
    }

    public function testIsStillFollowedDelegatesToTheCurrentStatus(): void
    {
        $now = Fixtures::at(Fixtures::NOW);
        $tracking = UrssafTracking::none()->observe('40', $now);

        static::assertFalse($tracking->isStillFollowed($now, new PollingWindow(new DateInterval('P1D'))));
        static::assertTrue($tracking->observe('10', $now)->isStillFollowed($now, new PollingWindow(new DateInterval('P1D'))));
    }

    private function polled(): UrssafTracking
    {
        return UrssafTracking::none()->polled(
            '10',
            TransferInfo::of(Money::fromCents(1000), CalendarDate::fromString('2022-03-10')),
            Rejection::of('ANNUL_DBL', 'Duplicate'),
            Fixtures::at(Fixtures::NOW),
        );
    }
}
