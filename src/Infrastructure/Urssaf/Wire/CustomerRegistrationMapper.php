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

use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Address\PostalAddress;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Bank\BankAccount;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\CustomerRegistration;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthMunicipality;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\BirthPlace;
use TheBeardedBear\UrssafTiersPrestations\Domain\Customer\Identity\PersonName;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTiersPrestations\Domain\Shared\UrssafCustomerId;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Method 010 (register a customer): builds the request body (`InputParticulierDTO`) and reads the response.
 *
 * Optional fields that are not set are omitted (never sent as null). Internal identifiers (registration id, tenant,
 * external reference) are never sent.
 */
final class CustomerRegistrationMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toBody(CustomerRegistration $registration): array
    {
        $civilStatus = $registration->customer()->civilStatus();
        $contact = $registration->customer()->contactDetails();

        $body = [
            'civilite' => $civilStatus->civility()->value,
            'nomNaissance' => $civilStatus->birthName()->value(),
        ];
        $usageName = $civilStatus->usageName();
        if ($usageName instanceof PersonName) {
            $body['nomUsage'] = $usageName->value();
        }

        return $body + [
            'prenoms' => $civilStatus->firstNames()->value(),
            'dateNaissance' => DateEncoding::encodeCalendarDate($civilStatus->birthDate()),
            'lieuNaissance' => $this->birthPlace($civilStatus->birthPlace()),
            'numeroTelephonePortable' => $contact->mobilePhone()->value(),
            'adresseMail' => $contact->email()->value(),
            'adressePostale' => $this->address($contact->address()),
            'coordonneeBancaire' => $this->bankAccount($registration->customer()->bankAccount()),
        ];
    }

    /**
     * @throws UnexpectedUrssafResponse when `idClient` is missing or is not a valid customer id
     */
    public function mapResponse(Payload $response): UrssafCustomerId
    {
        try {
            return UrssafCustomerId::fromString($response->requireString('idClient'));
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('customer id is not valid', $exception);
        }
    }

    /**
     * Department and municipality are only sent for a birth in France.
     *
     * @return array<string, mixed>
     */
    private function birthPlace(BirthPlace $place): array
    {
        return array_filter(
            [
                'codePaysNaissance' => $place->country()->value(),
                'departementNaissance' => $place->department()?->value(),
                'communeNaissance' => $this->birthMunicipality($place->municipality()),
            ],
            static fn(array|string|null $value): bool => $value !== null,
        );
    }

    /**
     * @return array{codeCommune: string, libelleCommune: string}|null
     */
    private function birthMunicipality(?BirthMunicipality $municipality): ?array
    {
        return $municipality instanceof BirthMunicipality
            ? ['codeCommune' => $municipality->code()->value(), 'libelleCommune' => $municipality->name()->value()]
            : null;
    }

    /**
     * @return array<string, string>
     */
    private function address(PostalAddress $address): array
    {
        $street = $address->street();

        return array_filter([
            'numeroVoie' => $street->number()?->value(),
            'lettreVoie' => $street->letter()?->value(),
            'codeTypeVoie' => $street->typeCode()?->value(),
            'libelleVoie' => $street->name()?->value(),
            'complement' => $street->complement()?->value(),
            'lieuDit' => $street->locality()?->value(),
            'libelleCommune' => $address->municipalityName()->value(),
            'codeCommune' => $address->municipalityCode()->value(),
            'codePostal' => $address->postalCode()->value(),
            'codePays' => $address->countryCode()->value(),
        ], static fn(?string $value): bool => $value !== null);
    }

    /**
     * @return array<string, string>
     */
    private function bankAccount(BankAccount $account): array
    {
        return [
            'bic' => $account->bic()->value(),
            'iban' => $account->iban()->value(),
            'titulaire' => $account->holder()->value(),
        ];
    }
}
