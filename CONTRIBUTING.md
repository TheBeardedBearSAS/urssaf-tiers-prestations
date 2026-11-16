# Contributing

Thank you for your interest in this package. Issues and pull requests are welcome.

Everyone taking part in the project follows the [Code of Conduct](CODE_OF_CONDUCT.md). How decisions are made and who
does what is described in [GOVERNANCE.md](GOVERNANCE.md); the intended direction is in [ROADMAP.md](ROADMAP.md).

## Before you start

- **Looking for a first contribution?** Small, self-contained tasks are labelled
  [`good first issue`](https://github.com/TheBeardedBearSAS/urssaf-tiers-prestations/labels/good%20first%20issue).

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
- tests with the coverage threshold, branch coverage of at least 96 % (`make coverage-branches`, Xdebug), and mutation
  testing (Infection) at **100% MSI**;
- licence headers: every source file carries the short header (`Copyright (c) TheBeardedBear and the
  urssaf-tiers-prestations contributors` + `SPDX-License-Identifier: MIT`). PHP files get it from `make cs-fix`
  (PHP CS Fixer `header_comment`); `make guard` fails when another source file (XML, YAML, shell, Makefile, Dockerfile)
  lacks it. `recipe/`, `vendor/`, `var/` and comment-less files (JSON, lock files) are excluded.

Thresholds are never lowered. If a rule really has to be relaxed, the exception goes in the tool configuration with a
comment explaining why, in the same pull request.

## Pull requests

- One subject per pull request, branched from `main`.
- **Tests are mandatory**: every new feature and every bug fix comes with automated tests (PHPUnit, in `tests/Unit`
  or `tests/Functional`) in the same pull request. A bug fix includes a test that fails without the fix. The 100 %
  coverage and 100 % MSI gates enforce it.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`,
  `test:`, `chore:`...).
- Every commit is signed off (`git commit -s`), see [Developer Certificate of Origin](#developer-certificate-of-origin).
- Add a line to the `[Unreleased]` section of [CHANGELOG.md](CHANGELOG.md) for any change that is visible to users.
- Update the documentation in `docs/` when behaviour or configuration changes.
- Code, comments and documentation are in English. The URSSAF field names, codes and wire values stay in French, as in
  the specification.

## Developer Certificate of Origin

Every commit must be signed off, certifying that you wrote the change or otherwise have the right to submit it under
the project license, as stated by the [Developer Certificate of Origin](https://developercert.org/) (DCO 1.1):

```bash
git commit -s   # adds "Signed-off-by: Your Name <your@email>" with your git author identity
```

The CI job `dco` (`bin/check-dco`) checks that every commit of a pull request carries a `Signed-off-by` trailer with
its author's e-mail. To fix a pull request: `git rebase --signoff main`, then force-push.

## Code review

Every change, including the maintainers' own, goes through a pull request to `main`; direct pushes are blocked by the
branch protection.

- **Who**: a maintainer (see [GOVERNANCE.md](GOVERNANCE.md)) reviews each pull request before merging it.
- **What the CI checks**: the quality gate above and the DCO sign-off (both blocking), plus CodeQL and the benchmarks
  (a regression of more than 20 % fails the job).
- **What the reviewer checks**:
  - the change does what the pull request says, and nothing else (one subject per pull request);
  - correctness against the URSSAF specification (field names, codes, formats, amounts, dates);
  - the architecture (domain free of infrastructure, ports and adapters, Deptrac layers) and the public API:
    no breaking change outside a major version, deprecations documented;
  - security: no secret, token or personal data in code, fixtures, logs or exception messages; input validated before
    it is sent to URSSAF; untrusted URSSAF responses decoded defensively; new dependencies justified and maintained;
  - tests: the new behaviour and every fixed bug are tested, and the tests check the behaviour (not the
    implementation);
  - the documentation (`docs/`, README) and the `[Unreleased]` section of [CHANGELOG.md](CHANGELOG.md) are updated.
- **Acceptable when**: the CI is green, the reviewer's comments are resolved and the reviewer approves. When the only
  maintainer is the author, they review the full diff of the pull request against this list before merging it.
  Pull requests are squash-merged with a Conventional Commits title.

By contributing, you agree that your contribution is licensed under the [MIT license](LICENSE) of the project.
