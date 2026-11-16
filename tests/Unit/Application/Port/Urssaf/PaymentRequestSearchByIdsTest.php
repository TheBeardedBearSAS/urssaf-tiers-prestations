<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Application\Port\Urssaf;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SearchCriterion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

final class PaymentRequestSearchByIdsTest extends TestCase
{
    /**
     * @return list<PaymentRequestId>
     */
    private static function ids(int $count): array
    {
        return array_map(static fn(int $i): PaymentRequestId => PaymentRequestId::fromString('id-' . $i), $count > 0 ? range(1, $count) : []);
    }

    public function testByPaymentRequestIds(): void
    {
        $ids = self::ids(10);

        $search = PaymentRequestSearch::byPaymentRequestIds(...$ids);

        static::assertSame(SearchCriterion::PaymentRequestIds, $search->criterion());
        static::assertSame($ids, $search->paymentRequestIds());
        static::assertSame([], $search->invoiceNumbers());
        static::assertNull($search->from());
        static::assertNull($search->to());
    }

    public function testIdsAreReindexed(): void
    {
        $id = PaymentRequestId::fromString('x');

        static::assertSame([$id], PaymentRequestSearch::byPaymentRequestIds(...['named' => $id])->paymentRequestIds());
    }

    public function testEmptyIdsAreRejected(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('payment_request_search'));

        PaymentRequestSearch::byPaymentRequestIds();
    }

    public function testTooManyIdsAreRejected(): void
    {
        $this->expectExceptionObject(InvalidValue::because(
            'payment_request_search.too_many_items',
            'Invalid payment_request_search: at most 10 items per search.',
            ['max_items' => 10],
        ));

        PaymentRequestSearch::byPaymentRequestIds(...self::ids(11));
    }
}
