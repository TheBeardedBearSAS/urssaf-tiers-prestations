<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Amounts on the wire are JSON numbers.
 *
 * Encoding goes through decimal strings ({@see JsonDecimal}), so there is no float drift (11.02, 5.515).
 * Decoding: json_decode yields floats for decimals; they are rendered with the target number of decimals
 * (`round($v, 2)`: half away from zero on the shortest decimal representation, then `%.2F`) before being parsed as
 * decimal strings, hence 11.02 is read as 1102 cents and never 1101.999...
 */
final class MoneyEncoding
{
    public static function encodeMoney(Money $money): JsonDecimal
    {
        return JsonDecimal::fromString($money->toDecimalString());
    }

    public static function encodeUnitPrice(UnitPrice $unitPrice): JsonDecimal
    {
        return JsonDecimal::fromString($unitPrice->toDecimalString());
    }

    public static function encodeQuantity(Quantity $quantity): JsonDecimal
    {
        return JsonDecimal::fromString($quantity->toDecimalString());
    }

    /**
     * @throws UnexpectedUrssafResponse when the number is negative, not finite or not representable
     */
    public static function decodeMoney(int|float|string $value): Money
    {
        if (\is_float($value) && !is_finite($value)) {
            throw UnexpectedUrssafResponse::invalidPayload('amount is not a finite number');
        }

        $decimal = \is_float($value) ? \sprintf('%.2F', round($value, 2)) : (string) $value;

        try {
            return Money::fromDecimalString($decimal);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy(\sprintf('"%s" is not a valid amount', $decimal), $exception);
        }
    }
}
