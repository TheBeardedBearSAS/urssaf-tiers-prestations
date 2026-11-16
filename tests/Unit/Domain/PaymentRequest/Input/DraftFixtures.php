<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Input;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\DepositDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\LineAmountsDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PaymentRequestDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\PeriodDraft;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input\ServiceLineDraft;
use TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Domain\PaymentRequest\Fixtures;

/**
 * Drafts reproducing the official request example of api-reference §2.3.
 */
final class DraftFixtures
{
    /**
     * @param list<ServiceLineDraft> $lines
     */
    public static function draft(array $lines, ?DepositDraft $deposit): PaymentRequestDraft
    {
        return new PaymentRequestDraft(
            'invoice-42',
            '2022-AZ-00001',
            '2022-02-01',
            new PeriodDraft(Fixtures::START, Fixtures::END),
            '11.02',
            '10.02',
            $lines,
            $deposit,
        );
    }

    public static function official(): PaymentRequestDraft
    {
        return self::draft([self::firstLine(), self::secondLine()], new DepositDraft('2.75', '2022-02-01'));
    }

    public static function firstLine(): ServiceLineDraft
    {
        return new ServiceLineDraft(
            '60',
            null,
            'HEURE',
            '1',
            '5.51',
            new LineAmountsDraft('5.51', '5.01', '0.5'),
            'SAP123456789',
            new PeriodDraft(Fixtures::START, Fixtures::END),
            'Complément 1',
        );
    }

    public static function secondLine(): ServiceLineDraft
    {
        return new ServiceLineDraft(
            '30',
            '30A001',
            'FORFAIT',
            '1',
            '5.51',
            new LineAmountsDraft('5.51', '5.01', '0.5'),
            'SAP123456780',
            new PeriodDraft(Fixtures::START, Fixtures::END),
            null,
        );
    }
}
