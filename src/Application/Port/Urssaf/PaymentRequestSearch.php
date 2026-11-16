<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

/**
 * Criteria of method 070 - POST /demandePaiement/rechercher. Exactly one criterion per search; lists hold 1 to
 * {@see self::MAX_ITEMS} items (callers chunk larger sets).
 */
final readonly class PaymentRequestSearch
{
    public const MAX_ITEMS = 10;
    private const SUBJECT = 'payment_request_search';

    /**
     * @param list<PaymentRequestId> $paymentRequestIds
     * @param list<InvoiceNumber>    $invoiceNumbers
     */
    private function __construct(
        private SearchCriterion $criterion,
        private array $paymentRequestIds,
        private array $invoiceNumbers,
        private ?CalendarDate $from,
        private ?CalendarDate $to,
    ) {}

    /**
     * @throws InvalidValue "payment_request_search.empty" or "payment_request_search.too_many_items"
     */
    public static function byPaymentRequestIds(PaymentRequestId ...$ids): self
    {
        self::assertCount(\count($ids));

        return new self(SearchCriterion::PaymentRequestIds, array_values($ids), [], null, null);
    }

    /**
     * @throws InvalidValue "payment_request_search.empty" or "payment_request_search.too_many_items"
     */
    public static function byInvoiceNumbers(InvoiceNumber ...$invoiceNumbers): self
    {
        self::assertCount(\count($invoiceNumbers));

        return new self(SearchCriterion::InvoiceNumbers, [], array_values($invoiceNumbers), null, null);
    }

    /**
     * Invoice date period, bounds included.
     *
     * @throws InvalidValue "payment_request_search.invalid_period" when $to is before $from
     */
    public static function byInvoicePeriod(CalendarDate $from, CalendarDate $to): self
    {
        if ($to->isBefore($from)) {
            throw InvalidValue::because(
                self::SUBJECT . '.invalid_period',
                'Invalid payment_request_search: the period end must not be before its start.',
            );
        }

        return new self(SearchCriterion::InvoicePeriod, [], [], $from, $to);
    }

    public function criterion(): SearchCriterion
    {
        return $this->criterion;
    }

    /**
     * @return list<PaymentRequestId> empty unless criterion is PaymentRequestIds
     */
    public function paymentRequestIds(): array
    {
        return $this->paymentRequestIds;
    }

    /**
     * @return list<InvoiceNumber> empty unless criterion is InvoiceNumbers
     */
    public function invoiceNumbers(): array
    {
        return $this->invoiceNumbers;
    }

    /**
     * Null unless criterion is InvoicePeriod.
     */
    public function from(): ?CalendarDate
    {
        return $this->from;
    }

    /**
     * Null unless criterion is InvoicePeriod.
     */
    public function to(): ?CalendarDate
    {
        return $this->to;
    }

    private static function assertCount(int $count): void
    {
        if ($count === 0) {
            throw InvalidValue::empty(self::SUBJECT);
        }
        if ($count > self::MAX_ITEMS) {
            throw InvalidValue::because(
                self::SUBJECT . '.too_many_items',
                \sprintf('Invalid payment_request_search: at most %d items per search.', self::MAX_ITEMS),
                ['max_items' => self::MAX_ITEMS],
            );
        }
    }
}
