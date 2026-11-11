<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Repository;

use DateInterval;
use Doctrine\DBAL\ArrayParameterType;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Type\InstantType;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequestStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PollingWindow;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\SubmissionState;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;

/**
 * The parameters of the "pollable" DQL condition of {@see DoctrinePaymentRequestRepository}, by name, with their value
 * and type: the final, settled and known raw status codes are derived from the domain enum, so the SQL selection cannot
 * drift from {@see PaymentRequestStatus::isFinal()} / isSettled() / fromCode(); the age limits come from the
 * {@see PollingWindow} ("instant > now - age", see the repository about month-based intervals).
 */
final class PollableParameters
{
    /**
     * @return array<string, array{object|list<string>, ArrayParameterType|string|null}>
     */
    public static function of(Instant $now, PollingWindow $window): array
    {
        $finals = [];
        $settled = [];
        $known = [];
        foreach (PaymentRequestStatus::cases() as $status) {
            $known[] = (string) $status->value;
            if ($status->isFinal()) {
                $finals[] = (string) $status->value;
            }
            if ($status->isSettled()) {
                $settled[] = (string) $status->value;
            }
        }

        return [
            'submitted' => [SubmissionState::Submitted, null],
            'finals' => [$finals, ArrayParameterType::STRING],
            'settled' => [$settled, ArrayParameterType::STRING],
            'known' => [$known, ArrayParameterType::STRING],
            'settledSince' => [self::since($now, $window->settledMaxAge()), InstantType::NAME],
            'unknownSince' => [self::since($now, $window->unknownStatusMaxAge()), InstantType::NAME],
            'submittedSince' => [self::since($now, $window->maxPollingAge()), InstantType::NAME],
        ];
    }

    private static function since(Instant $now, DateInterval $age): Instant
    {
        return Instant::fromDateTime($now->toDateTimeImmutable()->sub($age));
    }
}
