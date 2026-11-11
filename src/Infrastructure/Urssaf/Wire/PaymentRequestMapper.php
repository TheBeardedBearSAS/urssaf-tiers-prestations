<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ActivityCode;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\Deposit;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\LineComplement;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\PaymentRequest;
use TheBeardedBear\UrssafTiersPrestations\Domain\PaymentRequest\ServiceLine;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\EmploymentPeriod;

/**
 * Method 050 (submit payment requests): builds the request body, a JSON array of `InputDemandePaiement`.
 *
 * Amounts are {@see JsonDecimal}s (no float); dates: calendar dates as `...T00:00:00Z`, instants as UTC instants.
 * Optional fields that are not set are omitted. Internal identifiers (local id, tenant, external reference) are never
 * sent.
 */
final class PaymentRequestMapper
{
    /**
     * @return list<array<string, mixed>>
     */
    public function toBody(PaymentRequest ...$requests): array
    {
        $bodies = [];
        foreach ($requests as $request) {
            $bodies[] = $this->request($request);
        }

        return $bodies;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(PaymentRequest $request): array
    {
        $period = $request->employmentPeriod();
        $body = [
            'idTiersFacturation' => $request->billingThirdPartyId()->value(),
            'idClient' => $request->urssafCustomerId()->value(),
            'dateNaissanceClient' => DateEncoding::encodeCalendarDate($request->customerBirthDate()),
            'numFactureTiers' => $request->invoiceNumber()->value(),
            'dateFacture' => DateEncoding::encodeCalendarDate($request->invoiceDate()),
            'dateDebutEmploi' => DateEncoding::encodeInstant($period->start()),
            'dateFinEmploi' => DateEncoding::encodeInstant($period->end()),
        ];

        $deposit = $request->deposit();
        if ($deposit instanceof Deposit) {
            $body['mntAcompte'] = MoneyEncoding::encodeMoney($deposit->amount());
            $body['dateVersementAcompte'] = DateEncoding::encodeCalendarDate($deposit->paymentDate());
        }

        return $body + [
            'mntFactureTTC' => MoneyEncoding::encodeMoney($request->totalInclTax()),
            'mntFactureHT' => MoneyEncoding::encodeMoney($request->totalExclTax()),
            'inputPrestations' => array_map($this->line(...), $request->lines()->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(ServiceLine $line): array
    {
        $body = ['codeNature' => $line->natureCode()->value];
        $activity = $line->activityCode();
        if ($activity instanceof ActivityCode) {
            $body['codeActivite'] = $activity->value;
        }

        $body += [
            'quantite' => MoneyEncoding::encodeQuantity($line->quantity()),
            'unite' => $line->unit()->value,
            'mntUnitaireTTC' => MoneyEncoding::encodeUnitPrice($line->unitPrice()),
            'mntPrestationTTC' => MoneyEncoding::encodeMoney($line->amounts()->totalInclTax()),
            'mntPrestationHT' => MoneyEncoding::encodeMoney($line->amounts()->totalExclTax()),
            'mntPrestationTVA' => MoneyEncoding::encodeMoney($line->amounts()->vatAmount()),
        ];

        $period = $line->period();
        if ($period instanceof EmploymentPeriod) {
            $body['dateDebutEmploi'] = DateEncoding::encodeInstant($period->start());
            $body['dateFinEmploi'] = DateEncoding::encodeInstant($period->end());
        }

        $complement = $line->complement();
        if ($complement instanceof LineComplement) {
            $body['complement1'] = $complement->value();
        }

        $body['complement2'] = $line->intervenantId()->value();

        return $body;
    }
}
