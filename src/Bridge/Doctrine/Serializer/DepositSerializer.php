<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Explicit JSON shape of a {@see Deposit}: amount in cents and payment date.
 *
 * @phpstan-type DepositData array{amount: int, paymentDate: string}
 */
final class DepositSerializer
{
    /**
     * @return DepositData
     */
    public static function toArray(Deposit $deposit): array
    {
        return ['amount' => $deposit->amount()->cents(), 'paymentDate' => $deposit->paymentDate()->toString()];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape DepositData
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): Deposit
    {
        /** @var DepositData $data */
        return Deposit::of(Money::fromCents($data['amount']), CalendarDate::fromString($data['paymentDate']));
    }
}
