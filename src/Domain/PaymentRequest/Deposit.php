<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;

/**
 * Deposit already paid by the customer (mntAcompte + dateVersementAcompte).
 *
 * The "at most 50 % of the invoice total" rule (ERR_MONTANT_ACOMPTE) depends on the invoice: see
 * {@see self::exceedsHalfOf()} and {@see Specification\DepositWithinLimit}.
 */
final readonly class Deposit
{
    private function __construct(private Money $amount, private CalendarDate $paymentDate) {}

    /**
     * @throws InvalidValue "deposit.not_positive" for a zero amount
     */
    public static function of(Money $amount, CalendarDate $paymentDate): self
    {
        if ($amount->cents() === 0) {
            throw InvalidValue::notPositive('deposit');
        }

        return new self($amount, $paymentDate);
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function paymentDate(): CalendarDate
    {
        return $this->paymentDate;
    }

    /**
     * True when the deposit is strictly above half of the given total (exactly 50 % is accepted).
     */
    public function exceedsHalfOf(Money $total): bool
    {
        return $this->amount->cents() > intdiv($total->cents(), 2);
    }

    public function equals(self $other): bool
    {
        return $this->amount->equals($other->amount) && $this->paymentDate->equals($other->paymentDate);
    }
}
