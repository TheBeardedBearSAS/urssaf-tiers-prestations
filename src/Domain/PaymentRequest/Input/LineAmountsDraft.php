<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Input;

/**
 * Raw line amounts as decimal strings with at most 2 decimals ("5.51").
 */
final readonly class LineAmountsDraft
{
    public function __construct(public ?string $totalInclTax, public ?string $totalExclTax, public ?string $vatAmount) {}
}
