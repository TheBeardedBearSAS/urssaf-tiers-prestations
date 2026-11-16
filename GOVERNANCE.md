# Governance

This document describes how the project makes decisions and who does what.

## Model

The project is maintained by [The Bearded Bear](https://the-bearded-bear.com), which owns the GitHub organisation
`TheBeardedBearSAS` and the Packagist package `thebeardedbear/urssaf-tiers-prestations`. It follows a
maintainer-led model: anyone can propose a change, and the maintainers decide.

## Roles

| Role | Who | Responsibilities |
|---|---|---|
| Maintainer | [@flavien-metivier](https://github.com/flavien-metivier) (The Bearded Bear) | Reviews and merges pull requests (see *Code review* in [CONTRIBUTING.md](CONTRIBUTING.md)); triages issues; keeps the package in line with the URSSAF API; prepares and publishes releases (tag, GitHub release, signed assets, changelog); handles vulnerability reports as described in [SECURITY.md](SECURITY.md); enforces the [Code of Conduct](CODE_OF_CONDUCT.md); maintains the [roadmap](ROADMAP.md) and the repository settings (branch protection, CI, secrets). |
| Contributor | Anyone who opens an issue or a pull request | Follows [CONTRIBUTING.md](CONTRIBUTING.md) (quality gate, tests, Developer Certificate of Origin sign-off) and the [Code of Conduct](CODE_OF_CONDUCT.md). |
| Automated contributor | Dependabot | Proposes dependency and GitHub Actions updates; its pull requests go through the same CI and review as any other. |

The list of maintainers is kept up to date in this file. A contributor with a sustained record of quality
contributions can be invited to become a maintainer; the change is made by a pull request to this file.

## Decisions

- Day-to-day changes are decided in the pull request: a change is merged when the CI is green and a maintainer
  approves it.
- Larger changes (new feature, public API change, architecture change, new dependency) are first discussed in a GitHub
  issue. The maintainers make the decision there, with the reasons, after taking the comments into account.
- Breaking changes follow [Semantic Versioning](https://semver.org/) and are announced in the
  [changelog](CHANGELOG.md) with upgrade notes.
- Disagreements are discussed in the issue or pull request; if no consensus is reached, the maintainers decide.
  Code of Conduct matters are handled privately as described in the [Code of Conduct](CODE_OF_CONDUCT.md).
