<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Rejection;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafStatusChange;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\UrssafTracking;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

/**
 * Explicit JSON shape of an {@see UrssafTracking}: URSSAF id, every distinct status observed (oldest first, the last
 * one is the current status), transfer and rejection information, last poll instant.
 *
 * The tracking has no reconstitution constructor: it is rebuilt by replaying its public operations
 * (observe, identifiedAs, polled), which reproduces any state reachable through the aggregate.
 *
 * @phpstan-type TrackingData array{
 *     paymentRequestId: string|null,
 *     history: list<array{code: string, observedAt: string}>,
 *     transfer: array{amount: int, valueDate: string}|null,
 *     rejection: array{code: string, comment: string}|null,
 *     lastPolledAt: string|null
 * }
 */
final class TrackingSerializer
{
    private const NO_STATUS = '';

    /**
     * @return TrackingData
     */
    public static function toArray(UrssafTracking $tracking): array
    {
        $transfer = $tracking->transfer();
        $rejection = $tracking->rejection();

        return [
            'paymentRequestId' => $tracking->paymentRequestId()?->value(),
            'history' => array_map(
                static fn(UrssafStatusChange $change): array => [
                    'code' => $change->rawCode(),
                    'observedAt' => $change->observedAt()->toIso8601(),
                ],
                $tracking->history(),
            ),
            'transfer' => $transfer instanceof TransferInfo
                ? ['amount' => $transfer->amount()->cents(), 'valueDate' => $transfer->valueDate()->toString()]
                : null,
            'rejection' => $rejection instanceof Rejection ? ['code' => $rejection->code(), 'comment' => $rejection->comment()] : null,
            'lastPolledAt' => $tracking->lastPolledAt()?->toIso8601(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape TrackingData
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): UrssafTracking
    {
        /** @var TrackingData $data */
        $tracking = UrssafTracking::none();
        foreach ($data['history'] as $change) {
            $tracking = $tracking->observe($change['code'], Instant::fromIso8601($change['observedAt']));
        }
        if ($data['paymentRequestId'] !== null) {
            $tracking = $tracking->identifiedAs(
                PaymentRequestId::fromString($data['paymentRequestId']),
                self::NO_STATUS,
                Instant::fromIso8601('1970-01-01T00:00:00Z'),
            );
        }
        if ($data['lastPolledAt'] === null) {
            return $tracking;
        }

        return $tracking->polled(
            self::NO_STATUS,
            $data['transfer'] === null
                ? null
                : TransferInfo::of(Money::fromCents($data['transfer']['amount']), CalendarDate::fromString($data['transfer']['valueDate'])),
            $data['rejection'] === null ? null : Rejection::of($data['rejection']['code'], $data['rejection']['comment']),
            Instant::fromIso8601($data['lastPolledAt']),
        );
    }
}
