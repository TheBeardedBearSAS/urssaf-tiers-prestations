<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception;

use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;

/**
 * Error code catalogue of the URSSAF API (api-reference section 3.8). Values are the exact wire codes.
 *
 * Unknown codes are legitimate (URSSAF may add some): use {@see self::fromCode()} which returns null for them.
 */
// @mago-expect lint:too-many-enum-cases
enum ApiErrorCode: string
{
    case ValidationError = 'ERREUR_VALIDATION';
    case InvalidParameter = 'PARAM_INVALIDE';
    case TechnicalError = 'ERREUR_TECHNIQUE';
    case FutureDate = 'ERR_DATE_FUTUR';
    case PaymentBeforeSchemeStart = 'ERR_DATE_PAIEMENT_AVANT_EXP';
    case EmploymentPeriodSpansTwoMonths = 'ERR_PERIODE_EMPLOI_MOIS_NON_UNIQUE';
    case EndBeforeStartDate = 'ERR_DATE_FIN_AVANT_DATE_DEB';
    case DepositTooHigh = 'ERR_MONTANT_ACOMPTE';
    case LinesTotalMismatch = 'ERR_TOTAL_PRESTATIONS';
    case UnknownNatureCode = 'ERR_CODE_NATURE';
    case UnknownActivityCode = 'ERR_CODE_ACTIVITE';
    case ActivityNotInNature = 'ERR_CODE_ACTIVITE_NATURE';
    case TooManyDecimals = 'ERR_FORMAT_VALEUR';
    case NegativeValue = 'ERR_VALEUR_NEGATIVE';
    case LineVatMismatch = 'ERR_MNT_PREST_HT_TVA';
    case LineTotalMismatch = 'ERR_MNT_PREST_TTC';
    case TooManyPaymentRequests = 'ERR_NBRE_PREST_MAX';
    case UnknownCustomer = 'ERR_PARTICULIER_INCONNU';
    case UnknownIssuingProvider = 'ERR_PRESTATAIRE_EMETTEUR_INCONNU';
    case NoCustomerProviderLink = 'ERR_LIEN_PARTICULIER_PRESTATAIRE';
    case UnknownBillingProvider = 'ERR_PRESTATAIRE_FACTURATION_INCONNU';
    case DuplicateInvoice = 'ERR_FACTURE_DOUBLON';
    case EmptySearchCriteria = 'ERR_CRITERE_RECHERCHE_VIDE';
    case TooManySearchResults = 'ERR_NBRE_MAX_RESULTAT';
    case NoSearchResult = 'ERR_RECHERCHE_VIDE';
    case TaxAdministrationIdentityUnknown = 'ERR_DGFIP_R2P_INCONNU';
    case TaxAdministrationNoTaxedPeriod = 'ERR_DGFIP_IMPOTPART_INCONNU';
    case SeveralAccountsExist = 'ERR_ECV_PARTICULIERS_EXISTENT';
    case CustomerReferenceAlreadyExists = 'ERREUR_REFERENCE_PARTICULIER_EXISTANTE_TYPE';
    case BirthDateInFuture = 'ERR_DATE_NAISSANCE_FUTUR';
    case BirthMunicipalityLabelMissing = 'ERR_LIEU_NAISSANCE_OBLIGATOIRE';
    case BirthDepartmentRequired = 'ERREUR_LIEU_NAISSANCE_DEPT';
    case BirthDepartmentFormatInvalid = 'ERREUR_LIEU_NAISSANCE_DEPT_FORMAT';
    case BirthMunicipalityCodeRequired = 'ERR_LIEU_NAISSANCE_COMN';
    case BirthPlaceForeignWithFrenchDetails = 'ERREUR_LIEU_NAISSANCE_PAYS';
    case BirthPlaceMissing = 'ERREUR_LIEU_NAISSANCE_OBLIGATOIRE';
    case BirthMunicipalityLabelInvalid = 'ERREUR_LIEU_NAISSANCE_INVALIDE';
    case ContactInvalid = 'ERR_CONTACT';
    case AddressUnknownStreetType = 'ERR_CONTACT_ADRESSE_TYPE_VOIE';
    case AddressStreetTypeRequired = 'ERR_CONTACT_ADRESSE_TYPEVOIE';
    case AddressUnknownStreetLetter = 'ERR_CONTACT_ADRESSE_LETTRE_VOIE';
    case AddressStreetLabelInvalid = 'ERR_CONTACT_ADRESSE_LIBELLEVOIE';
    case AddressSupplementRequired = 'ERR_CONTACT_ADRESSE_INFORMATION_SUPPLEMENTAIRE';
    case AddressUnknownMunicipality = 'ERR_CONTACT_ADRESSE_COMMUNE';
    case InvalidBankDetails = 'ERR_COORDONNEES_BANCAIRES';
    case RegistrationCustomerAccountBlocked = 'ERR_COMPTE_BLOQUE';
    case CustomerAccountBlocked = 'ERR_CMPT_BLOQUE';
    case NoActiveRegistration = 'ERR_RECHERCHE_PARTICULIER';
    case InvalidWorkerNumberFormat = 'ERR_FORMAT_NUMINTERVENANT';
    case MissingWorkerNumber = 'ERR_NUMINTERVENANT_MANQUANT';
    case WorkerNumberBlocked = 'ERR_NUMINTERVENANT_BLOQUE';
    case MandateExpired = 'ERR_MANDAT_ECHU';
    case FinancialGuaranteeNotCompliant = 'ERREUR_GARANTIE_FINANCIERE_NON_CONFORME';
    case DepositNotAuthorised = 'ERR_ACOMPTE_BLOQUE';
    case ProviderCustomerLinkAlreadyExists = 'ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE';
    case ProviderCustomerLinkFailed = 'ERR_LIEN_TIERS_PRESTATION_PARTICULIER';

    /**
     * Normalises a raw wire code: surrounding or inner whitespace is dropped (the official documentation
     * contains " ERR_DATE_NAISSANCE_FUTUR " and "ERR_ ACOMPTE_BLOQUE").
     */
    public static function normalise(string $rawCode): string
    {
        return preg_replace('/\s+/', '', $rawCode) ?? $rawCode;
    }

    public static function fromCode(string $rawCode): ?self
    {
        return self::tryFrom(self::normalise($rawCode));
    }

    /**
     * @return list<Operation> operations that document this code
     */
    public function operations(): array
    {
        return match ($this) {
            self::ValidationError, self::TechnicalError, self::FinancialGuaranteeNotCompliant => Operation::cases(),
            self::InvalidParameter => [Operation::SubmitPaymentRequests, Operation::SearchPaymentRequests],
            self::FutureDate, self::PaymentBeforeSchemeStart, self::EmploymentPeriodSpansTwoMonths, self::EndBeforeStartDate, self::DepositTooHigh, self::LinesTotalMismatch, self::UnknownNatureCode, self::UnknownActivityCode, self::ActivityNotInNature, self::TooManyDecimals, self::NegativeValue, self::LineVatMismatch, self::LineTotalMismatch, self::TooManyPaymentRequests, self::UnknownCustomer, self::UnknownIssuingProvider, self::NoCustomerProviderLink, self::UnknownBillingProvider, self::DuplicateInvoice, self::CustomerAccountBlocked, self::InvalidWorkerNumberFormat, self::MissingWorkerNumber, self::WorkerNumberBlocked, self::MandateExpired, self::DepositNotAuthorised => [Operation::SubmitPaymentRequests],
            self::EmptySearchCriteria => [Operation::GetCustomerStatus, Operation::SearchPaymentRequests],
            self::TooManySearchResults, self::NoSearchResult => [Operation::SearchPaymentRequests],
            self::TaxAdministrationIdentityUnknown, self::TaxAdministrationNoTaxedPeriod, self::SeveralAccountsExist, self::CustomerReferenceAlreadyExists, self::BirthDateInFuture, self::BirthMunicipalityLabelMissing, self::BirthDepartmentRequired, self::BirthDepartmentFormatInvalid, self::BirthMunicipalityCodeRequired, self::BirthPlaceForeignWithFrenchDetails, self::BirthPlaceMissing, self::BirthMunicipalityLabelInvalid, self::ContactInvalid, self::AddressUnknownStreetType, self::AddressStreetTypeRequired, self::AddressUnknownStreetLetter, self::AddressStreetLabelInvalid, self::AddressSupplementRequired, self::AddressUnknownMunicipality, self::InvalidBankDetails, self::RegistrationCustomerAccountBlocked, self::ProviderCustomerLinkAlreadyExists, self::ProviderCustomerLinkFailed => [Operation::RegisterCustomer],
            self::NoActiveRegistration => [Operation::GetCustomerStatus],
        };
    }

    /**
     * True when the customer account is blocked (registration or payment request).
     */
    public function isCustomerAccountBlocked(): bool
    {
        return $this === self::RegistrationCustomerAccountBlocked || $this === self::CustomerAccountBlocked;
    }

    /**
     * Technical error (HTTP 500): retryable.
     */
    public function isTechnical(): bool
    {
        return $this === self::TechnicalError;
    }
}
