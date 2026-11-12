# Contributing

Thank you for your interest in this package. Issues and pull requests are welcome.

## Before you start

- **Bug**: open an issue with the version, a minimal reproduction and the expected vs actual behaviour. If the bug
  involves an URSSAF response, give the fictitious JSON payload and the URSSAF error code.
- **API change on the URSSAF side** (new swagger or technical documentation version): open an issue with the version
  and a link to the official document.
- **Feature**: open an issue first to discuss it. The architecture is deliberate (see the README *Architecture*
  section and `deptrac.yaml`), and a pull request that goes against it will be declined.
- **Vulnerability**: never in a public issue; see [SECURITY.md](SECURITY.md).

## Data and secrets

Never commit real credentials, access tokens, or real personal data (names, emails, phone numbers, tax numbers,
IBANs, URSSAF customer ids...), whether in code, fixtures, logs or recorded HTTP exchanges. Fixtures use fictitious
values or the examples from the official documentation.

## Development environment

Everything runs in Docker; you do not need PHP on your machine.

```bash
make install       # build the image and install the dependencies
make test          # unit + functional tests
make ci            # full quality gate, the same as the CI
make help          # every target
```

The live sandbox contract tests (`make test-contract`) are opt-in and need your own URSSAF sandbox credentials
(see the README). They are skipped when the credentials are missing.

## Quality gate

A pull request is merged only if `make ci` passes:

- code style (PHP CS Fixer), Rector, PHPStan at level max, Mago, Deptrac (architecture layers);
- composer validate / audit / normalize and dependency hygiene;
- tests with the coverage threshold, and mutation testing (Infection) at **100% MSI**.

Thresholds are never lowered. If a rule really has to be relaxed, the exception goes in the tool configuration with a
comment explaining why, in the same pull request.

## Pull requests

- One subject per pull request, branched from `main`.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`,
  `test:`, `chore:`...).
- Add a line to the `[Unreleased]` section of [CHANGELOG.md](CHANGELOG.md) for any change that is visible to users.
- Update the documentation in `docs/` when behaviour or configuration changes.
- Code, comments and documentation are in English. The URSSAF field names, codes and wire values stay in French, as in
  the specification.

By contributing, you agree that your contribution is licensed under the [MIT license](LICENSE) of the project.
