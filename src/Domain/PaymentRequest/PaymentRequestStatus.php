<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest;

/**
 * Status of a payment request at URSSAF (statut, api-reference §3.3). Backed by the official numeric code.
 */
enum PaymentRequestStatus: int
{
    case Integrated = 10;
    case AwaitingValidation = 20;
    case Validated = 30;
    case Refused = 40;
    case Debited = 50;
    case DebitRefused = 60;
    case Paid = 70;
    case Cancelled = 110;
    case CancelledAfterUnpaid = 111;
    case CancelledAfterRecovery = 112;
    case CancelledForSpecificReason = 113;
    case Recovered = 120;
    case ProviderUnpaid = 260;
    case ProviderRegularised = 270;

    private const FINAL = [
        self::Refused,
        self::Cancelled,
        self::CancelledAfterUnpaid,
        self::CancelledAfterRecovery,
        self::CancelledForSpecificReason,
        self::ProviderRegularised,
    ];

    /**
     * Lenient parsing of a raw status ("10", 10): anything that is not a known code (e.g. an error code such as
     * "ERR_CMPT_BLOQUE" returned by method 050) gives null.
     */
    public static function fromCode(string|int $code): ?self
    {
        $integer = filter_var($code, FILTER_VALIDATE_INT);

        return $integer === false ? null : self::tryFrom($integer);
    }

    /**
     * The status will never change any more.
     */
    public function isFinal(): bool
    {
        return \in_array($this, self::FINAL, true);
    }

    /**
     * Money reached the provider; the status may still move (e.g. unpaid), so it is followed for a limited time.
     */
    public function isSettled(): bool
    {
        return $this === self::Paid || $this === self::Recovered;
    }

    public function label(): string
    {
        return match ($this) {
            self::Integrated => 'Integrated',
            self::AwaitingValidation => 'Awaiting customer validation',
            self::Validated => 'Validated',
            self::Refused => 'Refused by the customer',
            self::Debited => 'Debited',
            self::DebitRefused => 'Debit refused',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
            self::CancelledAfterUnpaid => 'Cancelled after unpaid',
            self::CancelledAfterRecovery => 'Cancelled after recovery',
            self::CancelledForSpecificReason => 'Cancelled for a specific reason',
            self::Recovered => 'Recovered',
            self::ProviderUnpaid => 'Provider unpaid',
            self::ProviderRegularised => 'Regularised by the provider',
        };
    }
}
