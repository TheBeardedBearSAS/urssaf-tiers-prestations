<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineAmounts;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineComplement;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\NatureCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLines;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Unit;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\IntervenantId;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Money;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Quantity;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UnitPrice;

/**
 * Explicit JSON shape of the {@see ServiceLines} of a payment request: one object per line, amounts in cents, quantity
 * and unit price in thousandths, every optional part present with a null value.
 *
 * @phpstan-import-type PeriodData from PeriodSerializer
 *
 * @phpstan-type LineData array{
 *     nature: string,
 *     activity: string|null,
 *     unit: string,
 *     quantity: int,
 *     unitPrice: int,
 *     totalInclTax: int,
 *     totalExclTax: int,
 *     vat: int,
 *     intervenant: string,
 *     period: PeriodData|null,
 *     complement: string|null
 * }
 */
final class ServiceLinesSerializer
{
    /**
     * @return list<LineData>
     */
    public static function toArray(ServiceLines $lines): array
    {
        return array_map(
            static function (ServiceLine $line): array {
                $period = $line->period();

                return [
                    'nature' => $line->natureCode()->value,
                    'activity' => $line->activityCode()?->value,
                    'unit' => $line->unit()->value,
                    'quantity' => $line->quantity()->thousandths(),
                    'unitPrice' => $line->unitPrice()->thousandths(),
                    'totalInclTax' => $line->amounts()->totalInclTax()->cents(),
                    'totalExclTax' => $line->amounts()->totalExclTax()->cents(),
                    'vat' => $line->amounts()->vatAmount()->cents(),
                    'intervenant' => $line->intervenantId()->value(),
                    'period' => $period instanceof EmploymentPeriod ? PeriodSerializer::toArray($period) : null,
                    'complement' => $line->complement()?->value(),
                ];
            },
            $lines->all(),
        );
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape list<LineData>
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): ServiceLines
    {
        /** @var list<LineData> $data */
        return ServiceLines::of(...array_map(
            static fn(array $line): ServiceLine => new ServiceLine(
                NatureCode::fromString($line['nature']),
                $line['activity'] === null ? null : ActivityCode::fromString($line['activity']),
                Unit::fromString($line['unit']),
                Quantity::fromThousandths($line['quantity']),
                UnitPrice::fromThousandths($line['unitPrice']),
                LineAmounts::of(
                    Money::fromCents($line['totalInclTax']),
                    Money::fromCents($line['totalExclTax']),
                    Money::fromCents($line['vat']),
                ),
                IntervenantId::fromString($line['intervenant']),
                $line['period'] === null ? null : PeriodSerializer::fromArray($line['period']),
                $line['complement'] === null ? null : LineComplement::fromString($line['complement']),
            ),
            $data,
        ));
    }
}
