<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tiers-prestations.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tiers-prestations contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Unit\Infrastructure\Urssaf\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTiersPrestations\Application\Port\Operation;
use TheBeardedBear\UrssafTiersPrestations\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * Catalogue of api-reference section 3.8: wire value and documented operations of every code.
 */
final class ApiErrorCodeCatalogueTest extends TestCase
{
    private const ALL = [
        Operation::RegisterCustomer,
        Operation::GetCustomerStatus,
        Operation::SubmitPaymentRequests,
        Operation::SearchPaymentRequests,
    ];
    private const SUBMIT_SEARCH = [Operation::SubmitPaymentRequests, Operation::SearchPaymentRequests];
    private const STATUS_SEARCH = [Operation::GetCustomerStatus, Operation::SearchPaymentRequests];
    private const SUBMIT = [Operation::SubmitPaymentRequests];
    private const SEARCH = [Operation::SearchPaymentRequests];
    private const REGISTER = [Operation::RegisterCustomer];
    private const STATUS = [Operation::GetCustomerStatus];

    /**
     * @return iterable<string, array{ApiErrorCode, string, list<Operation>}>
     */
    public static function catalogue(): iterable
    {
        yield 'ERREUR_VALIDATION' => [ApiErrorCode::ValidationError, 'ERREUR_VALIDATION', self::ALL];
        yield 'PARAM_INVALIDE' => [ApiErrorCode::InvalidParameter, 'PARAM_INVALIDE', self::SUBMIT_SEARCH];
        yield 'ERREUR_TECHNIQUE' => [ApiErrorCode::TechnicalError, 'ERREUR_TECHNIQUE', self::ALL];
        yield 'ERR_DATE_FUTUR' => [ApiErrorCode::FutureDate, 'ERR_DATE_FUTUR', self::SUBMIT];
        yield 'ERR_DATE_PAIEMENT_AVANT_EXP' => [ApiErrorCode::PaymentBeforeSchemeStart, 'ERR_DATE_PAIEMENT_AVANT_EXP', self::SUBMIT];
        yield 'ERR_PERIODE_EMPLOI_MOIS_NON_UNIQUE' => [ApiErrorCode::EmploymentPeriodSpansTwoMonths, 'ERR_PERIODE_EMPLOI_MOIS_NON_UNIQUE', self::SUBMIT];
        yield 'ERR_DATE_FIN_AVANT_DATE_DEB' => [ApiErrorCode::EndBeforeStartDate, 'ERR_DATE_FIN_AVANT_DATE_DEB', self::SUBMIT];
        yield 'ERR_MONTANT_ACOMPTE' => [ApiErrorCode::DepositTooHigh, 'ERR_MONTANT_ACOMPTE', self::SUBMIT];
        yield 'ERR_TOTAL_PRESTATIONS' => [ApiErrorCode::LinesTotalMismatch, 'ERR_TOTAL_PRESTATIONS', self::SUBMIT];
        yield 'ERR_CODE_NATURE' => [ApiErrorCode::UnknownNatureCode, 'ERR_CODE_NATURE', self::SUBMIT];
        yield 'ERR_CODE_ACTIVITE' => [ApiErrorCode::UnknownActivityCode, 'ERR_CODE_ACTIVITE', self::SUBMIT];
        yield 'ERR_CODE_ACTIVITE_NATURE' => [ApiErrorCode::ActivityNotInNature, 'ERR_CODE_ACTIVITE_NATURE', self::SUBMIT];
        yield 'ERR_FORMAT_VALEUR' => [ApiErrorCode::TooManyDecimals, 'ERR_FORMAT_VALEUR', self::SUBMIT];
        yield 'ERR_VALEUR_NEGATIVE' => [ApiErrorCode::NegativeValue, 'ERR_VALEUR_NEGATIVE', self::SUBMIT];
        yield 'ERR_MNT_PREST_HT_TVA' => [ApiErrorCode::LineVatMismatch, 'ERR_MNT_PREST_HT_TVA', self::SUBMIT];
        yield 'ERR_MNT_PREST_TTC' => [ApiErrorCode::LineTotalMismatch, 'ERR_MNT_PREST_TTC', self::SUBMIT];
        yield 'ERR_NBRE_PREST_MAX' => [ApiErrorCode::TooManyPaymentRequests, 'ERR_NBRE_PREST_MAX', self::SUBMIT];
        yield 'ERR_PARTICULIER_INCONNU' => [ApiErrorCode::UnknownCustomer, 'ERR_PARTICULIER_INCONNU', self::SUBMIT];
        yield 'ERR_PRESTATAIRE_EMETTEUR_INCONNU' => [ApiErrorCode::UnknownIssuingProvider, 'ERR_PRESTATAIRE_EMETTEUR_INCONNU', self::SUBMIT];
        yield 'ERR_LIEN_PARTICULIER_PRESTATAIRE' => [ApiErrorCode::NoCustomerProviderLink, 'ERR_LIEN_PARTICULIER_PRESTATAIRE', self::SUBMIT];
        yield 'ERR_PRESTATAIRE_FACTURATION_INCONNU' => [ApiErrorCode::UnknownBillingProvider, 'ERR_PRESTATAIRE_FACTURATION_INCONNU', self::SUBMIT];
        yield 'ERR_FACTURE_DOUBLON' => [ApiErrorCode::DuplicateInvoice, 'ERR_FACTURE_DOUBLON', self::SUBMIT];
        yield 'ERR_CRITERE_RECHERCHE_VIDE' => [ApiErrorCode::EmptySearchCriteria, 'ERR_CRITERE_RECHERCHE_VIDE', self::STATUS_SEARCH];
        yield 'ERR_NBRE_MAX_RESULTAT' => [ApiErrorCode::TooManySearchResults, 'ERR_NBRE_MAX_RESULTAT', self::SEARCH];
        yield 'ERR_RECHERCHE_VIDE' => [ApiErrorCode::NoSearchResult, 'ERR_RECHERCHE_VIDE', self::SEARCH];
        yield 'ERR_DGFIP_R2P_INCONNU' => [ApiErrorCode::TaxAdministrationIdentityUnknown, 'ERR_DGFIP_R2P_INCONNU', self::REGISTER];
        yield 'ERR_DGFIP_IMPOTPART_INCONNU' => [ApiErrorCode::TaxAdministrationNoTaxedPeriod, 'ERR_DGFIP_IMPOTPART_INCONNU', self::REGISTER];
        yield 'ERR_ECV_PARTICULIERS_EXISTENT' => [ApiErrorCode::SeveralAccountsExist, 'ERR_ECV_PARTICULIERS_EXISTENT', self::REGISTER];
        yield 'ERREUR_REFERENCE_PARTICULIER_EXISTANTE_TYPE' => [ApiErrorCode::CustomerReferenceAlreadyExists, 'ERREUR_REFERENCE_PARTICULIER_EXISTANTE_TYPE', self::REGISTER];
        yield 'ERR_DATE_NAISSANCE_FUTUR' => [ApiErrorCode::BirthDateInFuture, 'ERR_DATE_NAISSANCE_FUTUR', self::REGISTER];
        yield 'ERR_LIEU_NAISSANCE_OBLIGATOIRE' => [ApiErrorCode::BirthMunicipalityLabelMissing, 'ERR_LIEU_NAISSANCE_OBLIGATOIRE', self::REGISTER];
        yield 'ERREUR_LIEU_NAISSANCE_DEPT' => [ApiErrorCode::BirthDepartmentRequired, 'ERREUR_LIEU_NAISSANCE_DEPT', self::REGISTER];
        yield 'ERREUR_LIEU_NAISSANCE_DEPT_FORMAT' => [ApiErrorCode::BirthDepartmentFormatInvalid, 'ERREUR_LIEU_NAISSANCE_DEPT_FORMAT', self::REGISTER];
        yield 'ERR_LIEU_NAISSANCE_COMN' => [ApiErrorCode::BirthMunicipalityCodeRequired, 'ERR_LIEU_NAISSANCE_COMN', self::REGISTER];
        yield 'ERREUR_LIEU_NAISSANCE_PAYS' => [ApiErrorCode::BirthPlaceForeignWithFrenchDetails, 'ERREUR_LIEU_NAISSANCE_PAYS', self::REGISTER];
        yield 'ERREUR_LIEU_NAISSANCE_OBLIGATOIRE' => [ApiErrorCode::BirthPlaceMissing, 'ERREUR_LIEU_NAISSANCE_OBLIGATOIRE', self::REGISTER];
        yield 'ERREUR_LIEU_NAISSANCE_INVALIDE' => [ApiErrorCode::BirthMunicipalityLabelInvalid, 'ERREUR_LIEU_NAISSANCE_INVALIDE', self::REGISTER];
        yield 'ERR_CONTACT' => [ApiErrorCode::ContactInvalid, 'ERR_CONTACT', self::REGISTER];
        yield 'ERR_CONTACT_ADRESSE_TYPE_VOIE' => [ApiErrorCode::AddressUnknownStreetType, 'ERR_CONTACT_ADRESSE_TYPE_VOIE', self::REGISTER];
        yield 'ERR_CONTACT_ADRESSE_TYPEVOIE' => [ApiErrorCode::AddressStreetTypeRequired, 'ERR_CONTACT_ADRESSE_TYPEVOIE', self::REGISTER];
        yield 'ERR_CONTACT_ADRESSE_LETTRE_VOIE' => [ApiErrorCode::AddressUnknownStreetLetter, 'ERR_CONTACT_ADRESSE_LETTRE_VOIE', self::REGISTER];
        yield 'ERR_CONTACT_ADRESSE_LIBELLEVOIE' => [ApiErrorCode::AddressStreetLabelInvalid, 'ERR_CONTACT_ADRESSE_LIBELLEVOIE', self::REGISTER];
        yield 'ERR_CONTACT_ADRESSE_INFORMATION_SUPPLEMENTAIRE' => [ApiErrorCode::AddressSupplementRequired, 'ERR_CONTACT_ADRESSE_INFORMATION_SUPPLEMENTAIRE', self::REGISTER];
        yield 'ERR_CONTACT_ADRESSE_COMMUNE' => [ApiErrorCode::AddressUnknownMunicipality, 'ERR_CONTACT_ADRESSE_COMMUNE', self::REGISTER];
        yield 'ERR_COORDONNEES_BANCAIRES' => [ApiErrorCode::InvalidBankDetails, 'ERR_COORDONNEES_BANCAIRES', self::REGISTER];
        yield 'ERR_COMPTE_BLOQUE' => [ApiErrorCode::RegistrationCustomerAccountBlocked, 'ERR_COMPTE_BLOQUE', self::REGISTER];
        yield 'ERR_CMPT_BLOQUE' => [ApiErrorCode::CustomerAccountBlocked, 'ERR_CMPT_BLOQUE', self::SUBMIT];
        yield 'ERR_RECHERCHE_PARTICULIER' => [ApiErrorCode::NoActiveRegistration, 'ERR_RECHERCHE_PARTICULIER', self::STATUS];
        yield 'ERR_FORMAT_NUMINTERVENANT' => [ApiErrorCode::InvalidWorkerNumberFormat, 'ERR_FORMAT_NUMINTERVENANT', self::SUBMIT];
        yield 'ERR_NUMINTERVENANT_MANQUANT' => [ApiErrorCode::MissingWorkerNumber, 'ERR_NUMINTERVENANT_MANQUANT', self::SUBMIT];
        yield 'ERR_NUMINTERVENANT_BLOQUE' => [ApiErrorCode::WorkerNumberBlocked, 'ERR_NUMINTERVENANT_BLOQUE', self::SUBMIT];
        yield 'ERR_MANDAT_ECHU' => [ApiErrorCode::MandateExpired, 'ERR_MANDAT_ECHU', self::SUBMIT];
        yield 'ERREUR_GARANTIE_FINANCIERE_NON_CONFORME' => [ApiErrorCode::FinancialGuaranteeNotCompliant, 'ERREUR_GARANTIE_FINANCIERE_NON_CONFORME', self::ALL];
        yield 'ERR_ACOMPTE_BLOQUE' => [ApiErrorCode::DepositNotAuthorised, 'ERR_ACOMPTE_BLOQUE', self::SUBMIT];
        yield 'ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE' => [ApiErrorCode::ProviderCustomerLinkAlreadyExists, 'ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE', self::REGISTER];
        yield 'ERR_LIEN_TIERS_PRESTATION_PARTICULIER' => [ApiErrorCode::ProviderCustomerLinkFailed, 'ERR_LIEN_TIERS_PRESTATION_PARTICULIER', self::REGISTER];
    }

    /**
     * @param list<Operation> $operations
     */
    #[DataProvider('catalogue')]
    public function testEachCodeHasItsWireValueAndOperations(ApiErrorCode $code, string $wire, array $operations): void
    {
        static::assertSame($wire, $code->value);
        static::assertSame($code, ApiErrorCode::fromCode($wire));
        static::assertSame($operations, $code->operations());
    }

    public function testTheCatalogueIsComplete(): void
    {
        $listed = array_map(
            static fn(array $row): ApiErrorCode => $row[0],
            iterator_to_array(self::catalogue(), false),
        );

        static::assertCount(\count(ApiErrorCode::cases()), $listed);
        static::assertCount(\count(ApiErrorCode::cases()), array_unique(array_map(
            static fn(ApiErrorCode $code): string => $code->value,
            $listed,
        )));
    }
}
