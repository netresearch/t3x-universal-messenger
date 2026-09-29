<!--
SPDX-FileCopyrightText: Netresearch DTT GmbH
SPDX-License-Identifier: LicenseRef-Netresearch-Restricted-Use
-->
# Contributing

Thank you for contributing! Pull requests are welcome — please open them against `main`.

## Commit Signing

All commits must be cryptographically signed and carry a DCO sign-off: `git commit -S --signoff`. The `require-signed-commits` ruleset on the default branch enforces the signature (the "Verified" badge on GitHub); the DCO check enforces the `Signed-off-by` trailer — these are two different things and both are required. Quickest setup is SSH signing: register your SSH key as a *signing key* on your GitHub account, then `git config --global gpg.format ssh && git config --global user.signingkey ~/.ssh/<key>.pub`.

## Conventions

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, ...). CI must be green before merge; the test and lint entry points are defined in `composer.json` and the `Makefile`.

## Governance and policies

This repository follows the organisation-wide policies of the `netresearch` GitHub organisation:

- [Governance](https://github.com/netresearch/.github/blob/main/GOVERNANCE.md): who decides whether a change is merged, how disagreements are resolved, and which roles (organisation owner, repository admin, maintainer, contributor) carry which responsibilities.
- [Roadmap](https://github.com/netresearch/.github/blob/main/ROADMAP.md): the maintenance work planned for the next twelve months and the work that is explicitly excluded.
- [Handling of dependency and code analysis findings](https://github.com/netresearch/.github/blob/main/SECURITY.md#handling-of-dependency-and-code-analysis-findings): which vulnerability, licence and static-analysis findings block a pull request, the remediation deadlines for the others, and how exceptions are recorded and reviewed.
- [Secret management](https://github.com/netresearch/.github/blob/main/SECURITY.md#secret-management): where CI and release secrets are stored, who can access them, and when they are rotated.
- [Access roster](https://github.com/netresearch/.github/blob/main/docs/access-roster.md): the accounts that hold admin, maintain or write access to this repository and to the organisation.

Every pull request in this repository runs these dependency and security checks (`.github/workflows/checks.yml`, reusable workflows from `netresearch/.github` and `netresearch/typo3-ci-workflows`):

- Dependency review of the dependencies a pull request adds or changes.
- Composer Audit against known vulnerabilities in PHP dependencies.
- PHP licence audit of the Composer dependencies.
- Opengrep static analysis (SAST).
- CodeQL code scanning.
- Betterleaks secret scanning.
- zizmor analysis of the GitHub Actions workflows.
- OpenSSF Scorecard.

`.github/workflows/ci.yml` additionally runs PHP lint, the code style check, PHPStan (including the PHPat architecture rules), Rector and Fractor dry-runs, the unit, acceptance and functional test suites, and the Playwright end-to-end tests.
