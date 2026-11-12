# URSSAF "API Tiers de prestations" — technical reference

This document is the single source of truth used by the package for enums, validation rules and wire fixtures.
It summarises the official specification:

- Swagger contract: **v1.30.14** (published 2025-12-16)
- Technical documentation PDF: **v1.30.19** (2026-09-24)
- Catalogue page: https://portailapi.urssaf.fr/fr/catalogue-api/prd/tiers-prestations
- PDF: https://cedito-medias.urssaf.fr/medias/PortailAPI/files/documentationAPIs/TP/Documentation-API-TiersPrestation_v1.30.19.pdf

The package constant `SpecVersion::CURRENT` must equal `1.30.19`. Field names, codes and wire values
below are reproduced verbatim from the specification (they are French by design); everything else in
the package is English.

> **Source and rights.** The specification, the field names, the codes and the official JSON examples quoted here come
> from the URSSAF documents listed above and remain URSSAF's property. They are reproduced only so the package can
> interoperate with the API, and they are not covered by this package's MIT license. In case of discrepancy, the
> official URSSAF documentation prevails. This package is not affiliated with URSSAF.

---

## 1. General

| Topic | Value |
|---|---|
| Access | Restricted. Credentials (client id / client secret) issued by URSSAF after approval. |
| Security | OAuth2 **client credentials** |
| Token endpoint | `POST https://api.urssaf.fr/api/oauth/v1/token` (form-urlencoded: `grant_type=client_credentials`, `client_id`, `client_secret`, `scope`) |
| Scope | `homeplus.tiersprestations` |
| Production base URL | `https://api.urssaf.fr/atp/v1/tiersPrestations` |
| Sandbox ("environnement Éditeur") base host | `https://api-edi.urssaf.fr` (not published in the public doc; must stay configurable) |
| Content type | `application/json` (request and response) |
| Optional headers | `x-request-id` (unique request id, the package always sends a UUID v4), `request.id`, `recouv.application.id` (flagged required in Swagger but absent from every official example — configurable, omitted by default) |
| HTTP errors | 400 (validation / business), 401, 403, 429 (quota), 500, 503, 512 (security) |

### 1.1 Quotas (HTTP 429 when exceeded)

| Operation | Limit |
|---|---|
| 010 `POST /particulier` | 100 calls / minute |
| 020 `GET /particulier` | 300 calls / minute |
| 050 `POST /demandePaiement` | 150 calls / minute |
| 070 `POST /demandePaiement/rechercher` | 300 calls / minute |

### 1.2 Known limitations (official)

- `idTiersFacturation` is required by the contract but **not used**: any arbitrary value is accepted (official example: `menage.fr`). The package exposes a configurable default.
- Method 020 search is **case sensitive**: `idClient` must be sent lower-case. On the sandbox, 020 can only be queried by `idClient` (not by email).
- Financial guarantee and deposit authorisation controls (`ERREUR_GARANTIE_FINANCIERE_NON_CONFORME`, `ERR_ACOMPTE_BLOQUE`) are currently disabled.
- `ERR_MANDAT_ECHU` control is disabled on the sandbox.

### 1.3 Error payload

Errors are returned either as a single object or as a list of objects:

```json
{ "code": "ERREUR_VALIDATION", "message": "Erreur de saisie sur le champ civilite : ne doit pas être nul", "description": "Erreur de saisie" }
```

```json
[ { "code": "ERREUR_VALIDATION", "message": "...", "description": "Erreur de saisie" } ]
```

Technical error: `{"code":"ERREUR_TECHNIQUE","message":"Erreur technique","description":"Une erreur système est survenue"}`.

---

## 2. Operations

### 2.1 Method 010 — Register a customer — `POST /particulier`

The customer must be known by the tax administration (DGFiP) and have at least one taxed period.
Registration is unitary. If the customer is already registered with another provider, only a
**pairing** is created; the customer must accept the pairing (and the terms of use) in their online
account before any payment request can be sent.

Body: `InputParticulierDTO`

| Field | Type | Req. | Rules |
|---|---|---|---|
| `civilite` | string | yes | `"1"` (male) or `"2"` (female) |
| `nomNaissance` | string | yes | 1–80 chars; letters without accents, apostrophe, hyphen, space; must not start/end with a special char. Pattern `^[A-Za-z]+(([A-Za-z'\- ])*)+([A-Za-z])\|(^[A-Za-z])$` |
| `nomUsage` | string | no | same rules, max 80; expected only when different from `nomNaissance` |
| `prenoms` | string | yes | same rules, max 80; usage first name(s) only, separated by one space |
| `dateNaissance` | date | yes | e.g. `1980-03-29T00:00:00.000Z`; not in the future |
| `lieuNaissance` | object | yes | see below |
| `numeroTelephonePortable` | string | yes | `^(0\|\+33)[6-7]([0-9]{2}){4}$` (e.g. `0605040302`, `+33605040302`) |
| `adresseMail` | string | yes | `^[a-zA-Z0-9%+_\-]+(\.[a-zA-Z0-9%+_\-]+)*@([a-zA-Z0-9\-]*[a-zA-Z0-9]\.)+[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]$` |
| `adressePostale` | object | yes | see below |
| `coordonneeBancaire` | object | yes | see below |

`lieuNaissance` (`InputLieuNaissanceDTO`):

| Field | Req. | Rules |
|---|---|---|
| `codePaysNaissance` | yes | INSEE country code, exactly 5 digits (`99100` = France) |
| `departementNaissance` | if France, **forbidden otherwise** | 3 chars `^[09][0-9][0-9abAB]$`: `001`…`095`, `02A`/`02B`, `971`…`976` |
| `communeNaissance` | if France, **forbidden otherwise** | `{codeCommune, libelleCommune}` |
| `communeNaissance.codeCommune` | yes | exactly 3 digits: the **last 3 chars of the INSEE municipality code** (of the *arrondissement* for Paris/Lyon/Marseille); for overseas departments (DROM): `0` + last 2 chars |
| `communeNaissance.libelleCommune` | yes | max 50, alphanumeric, no semicolon |

`adressePostale` (`InputAdresseDTO`):

| Field | Req. | Rules |
|---|---|---|
| `numeroVoie` | no | up to 4 digits, not `0`/`00`/`000`/`0000`; required if `lettreVoie` is set |
| `lettreVoie` | no | 1 char, see §3.6 |
| `codeTypeVoie` | no | up to 4 chars, see §3.7; required if `libelleVoie` is set |
| `libelleVoie` | no | max 28; required if `numeroVoie` is set |
| `complement` | no | max 38 |
| `lieuDit` | no | max 38 |
| — | — | at least one of `libelleVoie`, `complement`, `lieuDit` is required |
| `libelleCommune` | yes | 1–50, alphanumeric, hyphen, space, apostrophe (e.g. `PARIS 10`) |
| `codeCommune` | yes | INSEE code, 5 chars `^[0-9][0-9a-bA-B][0-9]{3}$` (e.g. `2B072`) |
| `codePostal` | yes | 5 digits |
| `codePays` | yes | INSEE country code, 5 digits |

`coordonneeBancaire` (`InputCoordonneeBancaireDTO`):

| Field | Req. | Rules |
|---|---|---|
| `bic` | yes | 8–11 chars `^[a-zA-Z]{6}[0-9a-zA-Z]{2}([0-9a-zA-Z]{3})?$` |
| `iban` | yes | 15–34 chars `^[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{4}[a-zA-Z0-9]{7}([a-zA-Z0-9]?){0,19}$` |
| `titulaire` | yes | 1–100 chars (e.g. `Mme Jeanne Martin`) |

Response 200: `{"idClient": "af0be430-908a-11eb-a8b3-0242ac130003"}` (UUID, 36 chars).

Business error codes (HTTP 400): `ERR_DGFIP_R2P_INCONNU`, `ERR_DGFIP_IMPOTPART_INCONNU`,
`ERR_DATE_NAISSANCE_FUTUR`, `ERREUR_LIEU_NAISSANCE_DEPT`, `ERREUR_LIEU_NAISSANCE_DEPT_FORMAT`,
`ERR_LIEU_NAISSANCE_COMN`, `ERREUR_LIEU_NAISSANCE_PAYS`, `ERREUR_LIEU_NAISSANCE_OBLIGATOIRE`,
`ERREUR_LIEU_NAISSANCE_INVALIDE`, `ERR_LIEU_NAISSANCE_OBLIGATOIRE`, `ERR_ECV_PARTICULIERS_EXISTENT`,
`ERREUR_REFERENCE_PARTICULIER_EXISTANTE_TYPE`, `ERR_CONTACT`, `ERR_CONTACT_ADRESSE_TYPE_VOIE`,
`ERR_CONTACT_ADRESSE_LETTRE_VOIE`, `ERR_CONTACT_ADRESSE_TYPEVOIE`, `ERR_CONTACT_ADRESSE_LIBELLEVOIE`,
`ERR_CONTACT_ADRESSE_INFORMATION_SUPPLEMENTAIRE`, `ERR_CONTACT_ADRESSE_COMMUNE`,
`ERR_COORDONNEES_BANCAIRES`, `ERR_COMPTE_BLOQUE`, `ERREUR_GARANTIE_FINANCIERE_NON_CONFORME`,
`ERR_LIEN_TIERS_PRESTATION_PARTICULIER`, `ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE`.
Generic: `ERREUR_VALIDATION`, `ERREUR_TECHNIQUE`.

Official request example:

```json
{
  "adresseMail": "jeanne.durand@contact.fr",
  "adressePostale": {
    "codeCommune": "69101", "codePays": "99100", "codePostal": "69001", "codeTypeVoie": "R",
    "complement": "Batiment A", "lettreVoie": "B", "libelleVoie": "du Soleil", "lieuDit": "Le Beyssat",
    "numeroVoie": "8", "libelleCommune": "LYON 01"
  },
  "civilite": "1",
  "coordonneeBancaire": { "bic": "BNPAFRPPXXX", "iban": "FR7630006000011234567890189", "titulaire": "Mme Jeanne Martin" },
  "dateNaissance": "1980-03-29T00:00:00.000Z",
  "lieuNaissance": {
    "codePaysNaissance": "99100",
    "communeNaissance": { "codeCommune": "001", "libelleCommune": "Lyon" },
    "departementNaissance": "069"
  },
  "nomNaissance": "Durand",
  "nomUsage": "Martin",
  "numeroTelephonePortable": "0605040302",
  "prenoms": "Eric-Antoine"
}
```

### 2.2 Method 020 — Payment request transmission status for a customer — `GET /particulier`

Query parameters: `idClient` (takes precedence, lower-case) and/or `adresseMail`.

Response 200 (`OutputParticulierSimpleDTO`):

```json
{
  "idClient": "af0be430-908a-11eb-a8b3-0242ac130003",
  "nomNaissance": "Ledru", "nomUsage": "Rollin", "prenoms": "Pierre-Alexandre",
  "statut": { "code": "APPAREILLAGE_EC", "description": "Transmission DP impossible, appareillage en cours de traitement", "etat": "KO" }
}
```

Transmission status codes (§4.8):

| `etat` | `code` | Meaning |
|---|---|---|
| OK | `APPAREILLAGE_VALIDE` | Payment requests can be sent since the pairing acceptance date (in `description`, format `JJ/MM/AAAA HH:mi:ss`) |
| KO | `APPAREILLAGE_EC` | Pairing being processed |
| KO | `APPAREILLAGE_NF` | Pairing not finalised (terms not accepted / pairing awaiting customer validation) — customer action required |
| KO | `PARTICULIER_BLOQUE` | Customer account blocked |
| KO | `MANDAT_ECHU` | Customer direct debit mandate expired / expiring |

Business error codes: `ERR_CRITERE_RECHERCHE_VIDE`, `ERR_RECHERCHE_PARTICULIER` (no active
registration with this provider — e.g. the customer broke the association),
`ERREUR_GARANTIE_FINANCIERE_NON_CONFORME`.

### 2.3 Method 050 — Submit payment requests — `POST /demandePaiement`

Body: JSON **array** of `InputDemandePaiement`, **maximum 10 per call**. Customers must be
registered and paired; employment dates must be later than the pairing acceptance date/time. No
payment request can be sent to a customer no longer associated with the provider.

Amounts are decimals with **at most 2 decimals**, except `mntUnitaireTTC` (**3 decimals**).

`InputDemandePaiement`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `idTiersFacturation` | string | yes | unused, arbitrary value (e.g. `menage.fr`) |
| `idClient` | string | yes | customer id returned by 010 |
| `dateNaissanceClient` | date | yes | must match the registered birth date |
| `numFactureTiers` | string | yes | invoice number, max 50; must be unique (`ERR_FACTURE_DOUBLON`) |
| `dateFacture` | date | yes | invoice issue date |
| `dateDebutEmploi` | date | yes | start of first service; not in the future |
| `dateFinEmploi` | date | yes | end of last service; same calendar month as start; not before start |
| `mntAcompte` | number | no | deposit, max 2 decimals, ≤ 50 % of `mntFactureTTC` |
| `dateVersementAcompte` | date | if `mntAcompte` | deposit payment date |
| `mntFactureTTC` | number | yes | invoice total incl. tax |
| `mntFactureHT` | number | yes | invoice total excl. tax |
| `inputPrestations` | array | yes | at least one line; Σ `mntPrestationTTC` = `mntFactureTTC` (± 0.05) |

`InputPrestation`:

| Field | Type | Req. | Rules |
|---|---|---|---|
| `codeActivite` | string | no | activity code, must belong to the nature (§3.2) |
| `codeNature` | string | yes | nature code (§3.1) |
| `quantite` | number | yes | > 0 (e.g. `1.75`) |
| `unite` | string | yes | `HEURE` or `FORFAIT` |
| `mntUnitaireTTC` | number | yes | unit price incl. tax, max 3 decimals |
| `mntPrestationTTC` | number | yes | = `mntUnitaireTTC` × `quantite` (± 0.05), 2 decimals |
| `mntPrestationHT` | number | yes | 2 decimals; HT + TVA = TTC (rounding tolerance) |
| `mntPrestationTVA` | number | yes | 2 decimals |
| `dateDebutEmploi` | date | no | line start |
| `dateFinEmploi` | date | no | line end, same month as start |
| `complement1` | string | no | free text |
| `complement2` | string | **yes** | NOVA or SIRET of the worker: `SAP` + 9 digits (SIREN) or `SIR` + 14 digits (SIRET); must not be blocked |

Response 200: array of `OutputDemandePaiement`, one per submitted request (same order):

| Field | Meaning |
|---|---|
| `idClient` | customer id |
| `idDemandePaiement` | payment request id, filled only when integrated (empty string otherwise) |
| `numFactureTiers` | invoice number |
| `statut` | **string**: status code (e.g. `"10"`) or, on business rejection, an error code (e.g. `"ERR_CMPT_BLOQUE"`) |
| `errors` | list of `Erreur` (empty when OK) |

Business errors are returned with **HTTP 200** inside each item:
`ERR_DATE_FUTUR`, `ERR_DATE_PAIEMENT_AVANT_EXP`, `ERR_PERIODE_EMPLOI_MOIS_NON_UNIQUE`,
`ERR_DATE_FIN_AVANT_DATE_DEB`, `ERR_MONTANT_ACOMPTE`, `ERR_TOTAL_PRESTATIONS`, `ERR_CODE_NATURE`,
`ERR_CODE_ACTIVITE`, `ERR_CODE_ACTIVITE_NATURE`, `ERR_FORMAT_VALEUR`, `ERR_VALEUR_NEGATIVE`,
`ERR_MNT_PREST_HT_TVA`, `ERR_MNT_PREST_TTC`, `ERR_NBRE_PREST_MAX`, `ERR_PARTICULIER_INCONNU`,
`ERR_PRESTATAIRE_EMETTEUR_INCONNU`, `ERR_LIEN_PARTICULIER_PRESTATAIRE`,
`ERR_PRESTATAIRE_FACTURATION_INCONNU`, `ERR_FACTURE_DOUBLON`, `ERR_CMPT_BLOQUE`,
`ERR_FORMAT_NUMINTERVENANT`, `ERR_NUMINTERVENANT_MANQUANT`, `ERR_NUMINTERVENANT_BLOQUE`,
`ERR_MANDAT_ECHU`, `ERREUR_GARANTIE_FINANCIERE_NON_CONFORME`, `ERR_ACOMPTE_BLOQUE`.
HTTP 400 is used for malformed input (`PARAM_INVALIDE`).

Official request example:

```json
[
  {
    "idTiersFacturation": "id-facturation",
    "idClient": "6a56a628-b09e-8707-787e-10f218a2d550",
    "dateNaissanceClient": "1980-03-01T00:00:00Z",
    "numFactureTiers": "2022-AZ-00001",
    "dateFacture": "2022-02-01T16:40:00Z",
    "dateDebutEmploi": "2022-02-01T16:40:00Z",
    "dateFinEmploi": "2022-02-01T16:45:00Z",
    "mntFactureTTC": 11.02,
    "mntFactureHT": 10.02,
    "mntAcompte": 2.75,
    "dateVersementAcompte": "2022-02-01T16:45:00Z",
    "inputPrestations": [
      { "codeNature": "60", "quantite": 1, "unite": "HEURE", "mntUnitaireTTC": 5.51, "mntPrestationTTC": 5.51,
        "mntPrestationHT": 5.01, "mntPrestationTVA": 0.5, "dateDebutEmploi": "2022-02-01T16:40:00Z",
        "dateFinEmploi": "2022-02-01T16:45:00Z", "complement1": "Complément 1", "complement2": "SAP123456789" },
      { "codeNature": "30", "codeActivite": "30A001", "quantite": 1, "unite": "FORFAIT", "mntUnitaireTTC": 5.51,
        "mntPrestationTTC": 5.51, "mntPrestationHT": 5.01, "mntPrestationTVA": 0.5,
        "dateDebutEmploi": "2022-02-01T16:40:00Z", "dateFinEmploi": "2022-02-01T16:45:00Z", "complement2": "SAP123456780" }
    ]
  }
]
```

Official response examples:

```json
[ { "idClient": "11000000000104", "idDemandePaiement": "", "numFactureTiers": "11000000000104", "statut": "10",
    "errors": [ { "code": "PARAM_INVALIDE", "message": "Requête incorrecte", "description": "Le format du champ numDemandeExterne est incorrect" } ] } ]
```

```json
[ { "idClient": "6a56a628-b09e-8707-787e-10f218a2d550", "numFactureTiers": "TEST-2023-09-25 03:01:59", "statut": "ERR_CMPT_BLOQUE",
    "errors": [ { "code": "ERR_CMPT_BLOQUE", "message": "Compte bloqué.", "description": "Le compte du particulier est bloqué." } ] } ]
```

### 2.4 Method 070 — Search payment requests — `POST /demandePaiement/rechercher`

Body `InputRechercheDemandePaiement` — criteria are exclusive, evaluated in this order:

1. `idDemandePaiements`: list of ids (**max 10**) — other criteria ignored;
2. `numFactureTiers`: list of invoice numbers (**max 10**) — period ignored;
3. `dateDebut` / `dateFin`: invoice date period.

No criterion → `ERR_CRITERE_RECHERCHE_VIDE`. Max 10 results per call (`ERR_NBRE_MAX_RESULTAT`).
No match → `ERR_RECHERCHE_VIDE`.

Response 200 `OutputRechercheDemandePaiement`:

```json
{
  "errors": [],
  "infoDemandePaiements": [
    {
      "idDemandePaiement": "aaaa1111-2222-3b3b-4d4-55e666ffff77",
      "demandePaiement": { "...": "InputDemandePaiement as registered" },
      "statut": { "code": "70", "libelle": "Payée" },
      "infoRejet": { "code": "CONTEST_AUTRE", "commentaire": "La prestation n'a pas été honorée." },
      "infoVirement": { "mntVirement": 2000, "dateVirement": "2019-12-05T00:00:00Z" }
    }
  ]
}
```

- `statut` is an **object** here (`code`, `libelle`), whereas it is a string in the 050 response.
- `infoRejet` is present when the request was regularised, contested or cancelled.
- `infoVirement` is present when the request was not contested; `dateVirement` is the forecast or actual value date.

---

## 3. Reference values

### 3.1 Nature codes (`codeNature`) and 3.2 activity codes (`codeActivite`)

| Nature | Category | Description (short) | Activities |
|---|---|---|---|
| `10` | Childcare | Home childcare under 3 / disabled under 18 | — |
| `20` | Childcare | Accompanying children under 3 / disabled under 18 outside home | — |
| `30` | Elderly / disabled assistance | Daily-life assistance at home (human help) | `30A001` administrative fees, `30A002` night, `30A003` weekend |
| `40` | Elderly / disabled assistance | Driving the person's own vehicle | — |
| `50` | Elderly / disabled assistance | Accompanying outings | — |
| `60` | Home services | Housework and ironing | `60A001` administrative fees, `60A002` night, `60A003` weekend |
| `70` | Home services | Small gardening work | — |
| `80` | Home services | Small DIY ("homme toutes mains") | — |
| `90` | Childcare | Home childcare above legal age (+6) | — |
| `100` | Home services | Tutoring / home lessons | `100A001` administrative fees |
| `110` | Elderly / disabled assistance | Beauty care at home for dependent persons | — |
| `120` | Home services | Meal preparation at home | — |
| `130` | Home services | Meal delivery | — |
| `140` | Home services | Ironed linen collection and delivery | — |
| `150` | Home services | Groceries delivery | — |
| `160` | Home services | IT assistance at home | — |
| `170` | Home services | Pet care and walking (dependent persons) | — |
| `180` | Home services | Temporary home maintenance and watching | — |
| `190` | Home services | Administrative assistance at home | — |
| `200` | Childcare | Accompanying children over 6 outside home | — |
| `210` | Home services | Remote / video assistance | — |
| `220` | Home services | Sign language interpreting, speech coding | — |
| `230` | Home services | Driving own vehicle — temporary disability | — |
| `240` | Home services | Accompanying outings — temporary disability | — |
| `250` | Home services | Human help at home — temporary disability | — |
| `260` | Home services | Coordination platform | — |
| `270` | Home services | Miscellaneous, **not eligible** to tax credit nor aids | — |

### 3.3 Payment request statuses (`statut`)

| Code | Label (wire) | Meaning | Final |
|---|---|---|---|
| `10` | Intégrée | Received and passed consistency checks | no |
| `20` | En attente de validation | Customer notified, must validate/reject | no |
| `30` | Validée | Validated by customer or automatically after 48 h; debit circuit started | no |
| `40` | Refusée | Rejected by customer with a reason code | **yes** |
| `50` | Prélevée | Debit of the remaining amount launched (2 clear days after validation) | no |
| `60` | En refus de prélèvement | Debit failed, request unpaid | no |
| `70` | Payée | Transfer to provider launched (≈ 4 working days after validation) | settled (may still move) |
| `110` | Annulée | Cancelled after a reasoned provider request | **yes** |
| `111` | Annulée après impayé | Cancelled after customer unpaid | **yes** |
| `112` | Annulée après recouvrement | Cancelled after recovery | **yes** |
| `113` | Annulée pour raison spécifique | Not available yet | **yes** |
| `120` | Recouvrée | Unpaid recovered by the provider (30 calendar days after debit rejection) | settled (may still move) |
| `260` | Impayé prestataire | Recovery debit failed, provider unpaid | no |
| `270` | Régularisé prestataire | Unpaid regularised by the provider | **yes** |

Without remaining amount (deposit covers everything) a validated request goes straight to `50` then `70`.

### 3.4 Unit codes (`unite`)

`HEURE`, `FORFAIT`.

### 3.5 Rejection reasons (`infoRejet.code`)

| Code | Context | Meaning |
|---|---|---|
| `REGUL_MNT_FACT` | regularisation | Wrong invoiced amount or unit price |
| `REGUL_NB_QTE_FACT` | regularisation | Wrong hours / quantities |
| `REGUL_PRESTA_FACT` | regularisation | Wrong services invoiced |
| `REGUL_AUTRE` | regularisation | Other |
| `CONTEST_ERR_FACT` | contestation (status 40) | Invoice contains an error |
| `CONTEST_FACT_REGLEE` | contestation (status 40) | Invoice already paid |
| `CONTEST_AUTRE` | contestation (status 40) | Other |
| `ANNUL_DBL` | cancellation (status 110) | Duplicate request |
| `ANNUL_ERR_MNT` | cancellation (status 110) | Wrong amount |
| `ANNUL_ERR_TECH` | cancellation (status 110) | Technical error |
| `ANNUL_AUTRE` | cancellation (status 110) | Other |

### 3.6 Street letters (`lettreVoie`)

`A`…`Z` (single letter). Notable meanings: `B` = bis, `T` = ter, `Q` = quater, `C` = quinquies.

### 3.7 Street type codes (`codeTypeVoie`) — 216 values

```
ALL AV BAT BD C CAN CAS CAV CEI CEL CEN CGE CH CHA CHE CHN CHP CHS CHT CHV CI CIM CIN CL CLB CLI CLL CLO
CN CO COE COG COL COM COR COT COU CRO CRX CTE CUR DEG DEP DES DIG DOM DON ECA ECL ECO ECV EGL EMP EN ENC
ENT ESC ESP ETA ETG FAC FCU FER FG FOL FON FOR FOS FOY FRI FT GA GAL GDI GED GEN GGE GLA GPE GR GRI GRN
GRP GSC GUE HA HAL HAM HCE HIP HLM HOP HOT HUT ILE ILO IMB IMP INS ISP JAR JET JUS LD LEV LOT LYC MA MAG
MAI MAN MAR MAS MAT MET MF MG ML MOU MRI MTE MUS NTE OBS OCT PA PAE PAL PAR PAS PAT PAV PBY PCE PER PHA
PIE PIM PKG PL PLA PLC PLE PLT PLY PO POI POR POT POU PPA PR PRA PRE PRO PRQ PRT PRU PT PTE Q QU R RAC
RAM REM RES RNT ROC ROQ ROT ROU RP RTE RUL SAN SAP SAS SCE SEC SEM SEN SNT SOC SQ STA STN STO TE TEA TEN
TER THE TOU TP TRA TRG TRI TUI USI VAL VCH VEL VEN VGE VIA VIL VLA VLE VNO VO VON VOY ZAC ZI ZUP
```

Common ones: `R` street, `AV` avenue, `BD` boulevard, `CHE` path, `IMP` dead end, `PL` square, `ALL` alley, `RTE` road, `LD` locality.

### 3.8 Error code catalogue (§4.5)

| Code | Operation(s) | Meaning |
|---|---|---|
| `ERREUR_VALIDATION` | all | Input validation error (field in `message`) |
| `PARAM_INVALIDE` | 050, 070 | Invalid parameter |
| `ERREUR_TECHNIQUE` | all | Technical error (HTTP 500) — retryable |
| `ERR_DATE_FUTUR` | 050 | Employment period in the future |
| `ERR_DATE_PAIEMENT_AVANT_EXP` | 050 | Period before the scheme start date |
| `ERR_PERIODE_EMPLOI_MOIS_NON_UNIQUE` | 050 | Period spans two calendar months |
| `ERR_DATE_FIN_AVANT_DATE_DEB` | 050 | End date before start date |
| `ERR_MONTANT_ACOMPTE` | 050 | Deposit above 50 % of invoice total |
| `ERR_TOTAL_PRESTATIONS` | 050 | Σ lines ≠ invoice total (± 0.05) |
| `ERR_CODE_NATURE` | 050 | Unknown nature code |
| `ERR_CODE_ACTIVITE` | 050 | Unknown activity code |
| `ERR_CODE_ACTIVITE_NATURE` | 050 | Activity not attached to nature |
| `ERR_FORMAT_VALEUR` | 050 | More than two decimals |
| `ERR_VALEUR_NEGATIVE` | 050 | Value must be positive |
| `ERR_MNT_PREST_HT_TVA` | 050 | HT + TVA ≠ TTC |
| `ERR_MNT_PREST_TTC` | 050 | quantity × unit price ≠ line TTC (± 0.05) |
| `ERR_NBRE_PREST_MAX` | 050 | More than 10 requests per call |
| `ERR_PARTICULIER_INCONNU` | 050 | Unknown customer id or birth date mismatch |
| `ERR_PRESTATAIRE_EMETTEUR_INCONNU` | 050 | Unknown / inactive provider |
| `ERR_LIEN_PARTICULIER_PRESTATAIRE` | 050 | No customer/provider link over the period (check activation and that period start ≥ pairing acceptance) |
| `ERR_PRESTATAIRE_FACTURATION_INCONNU` | 050 | Unknown invoicing provider |
| `ERR_FACTURE_DOUBLON` | 050 | Invoice number already submitted |
| `ERR_CRITERE_RECHERCHE_VIDE` | 020, 070 | No search criterion |
| `ERR_NBRE_MAX_RESULTAT` | 070 | Too many results |
| `ERR_RECHERCHE_VIDE` | 070 | No result |
| `ERR_DGFIP_R2P_INCONNU` | 010 | Identity not found by tax administration (check civil status) |
| `ERR_DGFIP_IMPOTPART_INCONNU` | 010 | No taxed period; retry after 1 August or 1 January |
| `ERR_ECV_PARTICULIERS_EXISTENT` | 010 | Several accounts exist for the customer (contact support) |
| `ERREUR_REFERENCE_PARTICULIER_EXISTANTE_TYPE` | 010 | Sandbox only: email/phone pair must be unique |
| `ERR_DATE_NAISSANCE_FUTUR` | 010 | Birth date in the future |
| `ERR_LIEU_NAISSANCE_OBLIGATOIRE` | 010 | Birth municipality label missing |
| `ERREUR_LIEU_NAISSANCE_DEPT` | 010 | Department required for France |
| `ERREUR_LIEU_NAISSANCE_DEPT_FORMAT` | 010 | Department format invalid |
| `ERR_LIEU_NAISSANCE_COMN` | 010 | Municipality code required for France |
| `ERREUR_LIEU_NAISSANCE_PAYS` | 010 | Department/municipality given for a foreign birth |
| `ERREUR_LIEU_NAISSANCE_OBLIGATOIRE` | 010 | Birth place missing |
| `ERREUR_LIEU_NAISSANCE_INVALIDE` | 010 | Birth municipality label invalid |
| `ERR_CONTACT` | 010 | Phone / email / address incomplete or invalid |
| `ERR_CONTACT_ADRESSE_TYPE_VOIE` | 010 | Unknown street type |
| `ERR_CONTACT_ADRESSE_TYPEVOIE` | 010 | Street type required when label is set |
| `ERR_CONTACT_ADRESSE_LETTRE_VOIE` | 010 | Unknown street letter |
| `ERR_CONTACT_ADRESSE_LIBELLEVOIE` | 010 | Street label required when type is set / invalid format |
| `ERR_CONTACT_ADRESSE_INFORMATION_SUPPLEMENTAIRE` | 010 | Street, complement or locality required |
| `ERR_CONTACT_ADRESSE_COMMUNE` | 010 | Unknown municipality |
| `ERR_COORDONNEES_BANCAIRES` | 010 | Invalid BIC or IBAN |
| `ERR_COMPTE_BLOQUE` | 010 | Customer account blocked (registration) |
| `ERR_CMPT_BLOQUE` | 050 | Customer account blocked (payment request) |
| `ERR_RECHERCHE_PARTICULIER` | 020 | No active registration with this provider |
| `ERR_FORMAT_NUMINTERVENANT` | 050 | Invalid NOVA / SIRET format |
| `ERR_NUMINTERVENANT_MANQUANT` | 050 | Missing NOVA / SIRET |
| `ERR_NUMINTERVENANT_BLOQUE` | 050 | Worker SIRET blocked |
| `ERR_MANDAT_ECHU` | 050 | No valid direct debit mandate |
| `ERREUR_GARANTIE_FINANCIERE_NON_CONFORME` | 010, 020, 050, 070 | Financial guarantee not compliant (control disabled) |
| `ERR_ACOMPTE_BLOQUE` | 050 | Deposit not authorised (control disabled; the PDF also spells it `ERR_ ACOMPTE_BLOQUE`) |
| `ERR_LIEN_TIERS_PRESTATION_PARTICULIER_EXISTE` | 010 | Provider/customer link already exists |
| `ERR_LIEN_TIERS_PRESTATION_PARTICULIER` | 010 | Error while linking provider and customer |

---

## 4. Package decisions derived from the specification

1. **Date encoding.** Date-only fields (`dateNaissance`, `dateNaissanceClient`, `dateFacture`,
   `dateVersementAcompte`) are sent as `YYYY-MM-DDT00:00:00Z`. Employment period bounds are real
   instants converted to UTC (`YYYY-MM-DDTHH:MM:SSZ`) because URSSAF compares them with the pairing
   acceptance date/time. Same-month and not-in-future rules are checked locally in the business
   time zone (default `Europe/Paris`) and in UTC.
2. **Money.** Integers internally (cents; thousandths for unit price and quantity). Encoded as JSON
   numbers with exact precision. Local checks replicate URSSAF tolerances (± 0.05).
3. **Statuses.** 050 returns `statut` as a string that may be an error code; 070 returns an object.
   Both are parsed defensively. Unknown status codes are preserved raw.
4. **Registration lifecycle.** 010 success ⇒ `awaiting_pairing`; the registration becomes
   `registered` only when 020 returns `APPAREILLAGE_VALIDE`, whose acceptance date is stored and used
   as the lower bound of employment periods.
5. **Batching.** 050 and 070 calls are chunked by 10.
6. **Names.** Names are transliterated to ASCII before validation (DGFiP matching is strict).
