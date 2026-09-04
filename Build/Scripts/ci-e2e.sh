#!/usr/bin/env bash
#
# CI wrapper for runTests.sh -s e2e.
#
# The reusable workflow netresearch/typo3-ci-workflows/.github/workflows/e2e.yml
# (with `setup-script: Build/Scripts/ci-e2e.sh`) invokes this script with no
# arguments. It exists only to keep runTests.sh a plain CLI tool that knows
# nothing about the workflow's env-var contract.
#
# Local invocation should call runTests.sh directly:
#   ./Build/Scripts/runTests.sh -s e2e -p 8.5

set -euo pipefail

PHP_VERSION=8.5

# This extension requires typo3/cms-core ^14.0 (composer.json); the shared
# provisioner defaults to TYPO3 13 when E2E_TYPO3_VERSION is unset, which
# then fails to resolve against this extension's own constraint.
export E2E_TYPO3_VERSION=14

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "ci-e2e.sh: PHP ${PHP_VERSION}, TYPO3 ${E2E_TYPO3_VERSION}"
# runTests.sh, not e2e.sh: the environment is built by the shared runner's
# e2e-provision.sh from the hooks in Build/Scripts/runTests.conf.
exec "${SCRIPT_DIR}/runTests.sh" \
    -s e2e \
    -p "${PHP_VERSION}"
