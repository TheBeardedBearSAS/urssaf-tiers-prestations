# Security policy

## Supported versions

Only the latest release of the latest minor version receives security fixes. While the package is in `0.x`, that is
the latest `0.y.z`.

## Reporting a vulnerability

**Do not open a public issue.** Report vulnerabilities privately through GitHub:
[Report a vulnerability](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations/security/advisories/new)
(tab *Security* > *Advisories* > *Report a vulnerability*).

Please include:

- the affected version(s);
- a description of the issue and its impact (for example: credentials or personal data exposed, an incorrect amount
  or declaration sent to URSSAF, a bypassed validation);
- the steps to reproduce, ideally a minimal test case.

Never include real credentials (`client_id` / `client_secret`), access tokens, or the real personal data of customers
(identity, tax number, IBAN...) in a report. Use fictitious values.

You will get an acknowledgement within 5 working days. The fix is then prepared privately and released, and the
advisory is published with credit to the reporter unless you ask otherwise.

## Security design and review

- [Assurance case](docs/security/assurance-case.md): security requirements, threat model, trust boundaries, secure
  design principles and the countermeasures against common implementation weaknesses.
- [Security review, November 2026](docs/security/security-review-2026-11.md): design and code review against that case,
  with its findings.

## Verifying a release

Every GitHub release carries the distribution archive (same content as the Composer dist), its `SHA256SUMS` and a
SLSA build provenance attestation (`*.intoto.jsonl`, a Sigstore bundle signed keylessly by the `release.yml`
workflow). To check that an archive was built by this repository's workflow from the tagged commit:

```bash
gh release download vX.Y.Z --repo TheBeardedBearSAS/urssaf-tiers-prestations
sha256sum --check SHA256SUMS
gh attestation verify urssaf-tiers-prestations-vX.Y.Z.zip --repo TheBeardedBearSAS/urssaf-tiers-prestations
```

## Scope

In scope: the code of this package (HTTP client, OAuth2 token handling, validation, wire encoding, Symfony bundle,
Doctrine persistence and its GDPR tools).

Out of scope: the URSSAF platform itself (report those issues to URSSAF), and vulnerabilities in dependencies that
this package does not make exploitable (report them upstream).
