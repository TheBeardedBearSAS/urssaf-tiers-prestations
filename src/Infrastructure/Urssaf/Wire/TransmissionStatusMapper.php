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

use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Urssaf\CustomerTransmissionStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\PairingStatus;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\Instant;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\ApiConfiguration;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the method 020 response (`statut: {code, description, etat}`) to a {@see CustomerTransmissionStatus}.
 *
 * Only `APPAREILLAGE_VALIDE` allows transmission; its pairing acceptance date is read from the description
 * (`JJ/MM/AAAA HH:mi:ss`, in the configured time zone: Europe/Paris by default, see
 * {@see ApiConfiguration::pairingTimeZone()}). Any other code is a blocked status, kept raw.
 */
final readonly class TransmissionStatusMapper
{
    private const ACCEPTANCE_DATE_PATTERN = '#\b\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}\b#';
    private const ACCEPTANCE_DATE_FORMAT = 'd/m/Y H:i:s';

    public function __construct(private DateTimeZone $pairingTimeZone = new DateTimeZone(ApiConfiguration::DEFAULT_PAIRING_TIME_ZONE)) {}

    /**
     * @throws UnexpectedUrssafResponse when the status is missing, or when a valid pairing has no readable date
     */
    public function map(UrssafCustomerId $customerId, Payload $payload): CustomerTransmissionStatus
    {
        $status = $payload->object('statut');
        if (!$status instanceof Payload) {
            throw UnexpectedUrssafResponse::invalidPayload('field "statut" is missing');
        }

        $code = $status->requireString('code');
        $description = $status->string('description') ?? '';

        if (PairingStatus::fromCode($code)?->allowsTransmission() !== true) {
            return CustomerTransmissionStatus::blocked($customerId, $code, $description);
        }

        return CustomerTransmissionStatus::allowed($customerId, $code, $description, $this->acceptanceDate($description));
    }

    private function acceptanceDate(string $description): Instant
    {
        $matches = [];
        if (preg_match(self::ACCEPTANCE_DATE_PATTERN, $description, $matches) !== 1) {
            throw UnexpectedUrssafResponse::invalidPayload('pairing acceptance date not found in the status description');
        }

        $parsed = DateTimeImmutable::createFromFormat(
            self::ACCEPTANCE_DATE_FORMAT,
            $matches[0],
            $this->pairingTimeZone,
        );

        if ($parsed === false || $parsed->format(self::ACCEPTANCE_DATE_FORMAT) !== $matches[0]) {
            throw UnexpectedUrssafResponse::invalidPayload(
                \sprintf('pairing acceptance date "%s" is not a valid date', $matches[0]),
            );
        }

        return Instant::fromDateTime($parsed);
    }
}
