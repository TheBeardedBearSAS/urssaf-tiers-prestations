<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire\PaymentRequestSearchMapper;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class PaymentRequestSearchMapperTest extends TestCase
{
    public function testItBuildsTheBodyForPaymentRequestIds(): void
    {
        $search = PaymentRequestSearch::byPaymentRequestIds(
            PaymentRequestId::fromString('aaaa1111-2222-3b3b-4d4-55e666ffff77'),
            PaymentRequestId::fromString('bbbb1111-2222-3b3b-4d4-55e666ffff88'),
        );

        $this->assertBodyMatchesFixture('search_request_by_ids', $search);
    }

    public function testItBuildsTheBodyForInvoiceNumbers(): void
    {
        $search = PaymentRequestSearch::byInvoiceNumbers(
            InvoiceNumber::fromString('2022-AZ-00001'),
            InvoiceNumber::fromString('2022-AZ-00002'),
        );

        $this->assertBodyMatchesFixture('search_request_by_invoices', $search);
    }

    public function testItBuildsTheBodyForAnInvoicePeriod(): void
    {
        $search = PaymentRequestSearch::byInvoicePeriod(
            CalendarDate::fromString('2022-02-01'),
            CalendarDate::fromString('2022-02-28'),
        );

        $this->assertBodyMatchesFixture('search_request_by_period', $search);
    }

    private function encode(PaymentRequestSearch $search): string
    {
        return JsonEncoder::encode((new PaymentRequestSearchMapper())->toBody($search));
    }

    private function assertBodyMatchesFixture(string $fixture, PaymentRequestSearch $search): void
    {
        static::assertSame(
            json_decode(Fixtures::load($fixture), true, 512, JSON_THROW_ON_ERROR),
            json_decode($this->encode($search), true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
