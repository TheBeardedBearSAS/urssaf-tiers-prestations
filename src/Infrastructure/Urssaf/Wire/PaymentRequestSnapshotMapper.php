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

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\PaymentRequestSnapshot;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\RejectionInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\StatusInfo;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\TransferInfo;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\PaymentRequestId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps one `infoDemandePaiements[]` item of the method 070 response to a {@see PaymentRequestSnapshot}.
 * The status is accepted as an object `{code, libelle}` or as a plain code; codes may be strings or integers.
 */
final class PaymentRequestSnapshotMapper
{
    /**
     * @return list<PaymentRequestSnapshot>
     *
     * @throws UnexpectedUrssafResponse
     */
    public function mapAll(Payload $response): array
    {
        return array_map($this->map(...), $response->items('infoDemandePaiements'));
    }

    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $item): PaymentRequestSnapshot
    {
        $invoiceNumber = $item->object('demandePaiement')?->string('numFactureTiers')
            ?? $item->string('numFactureTiers')
            ?? '';

        return PaymentRequestSnapshot::of(
            $this->paymentRequestId($item->requireString('idDemandePaiement')),
            $invoiceNumber,
            $this->status($item),
            $this->rejection($item->object('infoRejet')),
            $this->transfer($item->object('infoVirement')),
        );
    }

    private function paymentRequestId(string $value): PaymentRequestId
    {
        try {
            return PaymentRequestId::fromString($value);
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('payment request id is not valid', $exception);
        }
    }

    private function status(Payload $item): StatusInfo
    {
        $code = $item->code('statut');
        if ($code !== null) {
            return StatusInfo::of($code, '');
        }

        $status = $item->object('statut');
        if (!$status instanceof Payload) {
            throw UnexpectedUrssafResponse::invalidPayload('field "statut" is missing');
        }

        return StatusInfo::of(self::requiredCode($status, 'code'), $status->string('libelle') ?? '');
    }

    /**
     * @throws UnexpectedUrssafResponse when the code is absent or empty
     */
    private static function requiredCode(Payload $payload, string $key): string
    {
        $code = $payload->code($key) ?? '';
        if ($code === '') {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or empty', $key));
        }

        return $code;
    }

    private function rejection(?Payload $rejection): ?RejectionInfo
    {
        if (!$rejection instanceof Payload) {
            return null;
        }

        return RejectionInfo::of(self::requiredCode($rejection, 'code'), $rejection->string('commentaire') ?? '');
    }

    private function transfer(?Payload $transfer): ?TransferInfo
    {
        if (!$transfer instanceof Payload) {
            return null;
        }

        $amount = $transfer->number('mntVirement');
        if ($amount === null) {
            throw UnexpectedUrssafResponse::invalidPayload('field "mntVirement" is missing');
        }

        return TransferInfo::of(
            MoneyEncoding::decodeMoney($amount),
            DateEncoding::decodeCalendarDate($transfer->requireString('dateVirement')),
        );
    }
}
