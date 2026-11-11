<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSearch;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\SearchCriterion;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\InvoiceNumber;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;

/**
 * Builds the method 070 body (`InputRechercheDemandePaiement`). Exactly one criterion is sent.
 */
final class PaymentRequestSearchMapper
{
    /**
     * @return array<string, list<string>|string>
     */
    public function toBody(PaymentRequestSearch $search): array
    {
        return match ($search->criterion()) {
            SearchCriterion::PaymentRequestIds => [
                'idDemandePaiements' => array_map(
                    static fn(PaymentRequestId $id): string => $id->value(),
                    $search->paymentRequestIds(),
                ),
            ],
            SearchCriterion::InvoiceNumbers => [
                'numFactureTiers' => array_map(
                    static fn(InvoiceNumber $number): string => $number->value(),
                    $search->invoiceNumbers(),
                ),
            ],
            SearchCriterion::InvoicePeriod => $this->periodBody($search),
        };
    }

    /**
     * @return array<string, string>
     */
    private function periodBody(PaymentRequestSearch $search): array
    {
        $body = [];
        $from = $search->from();
        if ($from instanceof CalendarDate) {
            $body['dateDebut'] = DateEncoding::encodeCalendarDate($from);
        }

        $to = $search->to();
        if ($to instanceof CalendarDate) {
            $body['dateFin'] = DateEncoding::encodeCalendarDate($to);
        }

        return $body;
    }
}
