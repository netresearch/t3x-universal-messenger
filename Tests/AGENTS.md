<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — Tests

<!-- AGENTS-GENERATED:START overview -->
## Overview
Test suites of the `universal_messenger` extension: PHPUnit unit and functional tests plus PHPat architecture rules. **Use the `typo3-testing` skill** for comprehensive guidance.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START setup -->
## Setup
- `composer install` first — PHPUnit and the TYPO3 testing framework land in `.build/` (composer `vendor-dir`/`bin-dir`)
- PHPUnit configs live in `Build/UnitTests.xml` and `Build/FunctionalTests.xml` (not in `Tests/`)
- Functional tests need a database; CI runs them against SQLite (`functional-test-db: 'sqlite'` in `.github/workflows/ci.yml`)
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `Unit/Controller/UniversalMessengerControllerTest.php` | Controller unit tests (via `TestableUniversalMessengerController` subclass) |
| `Unit/WebserviceConfigurationTest.php` | Extension configuration parsing tests |
| `Unit/ViewHelpers/Format/PlaceholderViewHelperTest.php` | ViewHelper unit test |
| `Functional/Controller/UniversalMessengerControllerArgumentsTest.php` | Functional controller test |
| `Functional/Configuration/PagesTcaTest.php` | TCA integrity test for the `pages` overrides |
| `Architecture/ArchitectureTest.php` | PHPat layer rules — runs inside PHPStan, **not** via PHPUnit |
<!-- AGENTS-GENERATED:END filemap -->

<!-- AGENTS-GENERATED:START structure -->
## Test Structure
```
Tests/
├── Unit/            # Fast, isolated unit tests (Build/UnitTests.xml)
├── Functional/      # Tests with TYPO3/DB context (Build/FunctionalTests.xml)
└── Architecture/    # PHPat rules, executed by composer ci:test:php:phpstan
```
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START commands -->
## Running Tests
| Type | Command |
|------|---------|
| Unit tests | `composer ci:test:php:unit` |
| Functional tests | `composer ci:test:php:functional` |
| Architecture rules | `composer ci:test:php:phpstan` (PHPat runs inside PHPStan) |
| Single file | `.build/bin/phpunit --configuration Build/UnitTests.xml Tests/Unit/Path/To/Test.php` |
<!-- AGENTS-GENERATED:END commands -->

<!-- AGENTS-GENERATED:START patterns -->
## Key Patterns (TYPO3-specific)
- Unit tests extend `\TYPO3\TestingFramework\Core\Unit\UnitTestCase`
- Functional tests extend `\TYPO3\TestingFramework\Core\Functional\FunctionalTestCase` and declare `$testExtensionsToLoad`
- Protected controller internals are tested through a dedicated testable subclass (`Unit/Controller/TestableUniversalMessengerController.php`) — follow that pattern instead of reflection
- New architecture constraints go into `Architecture/ArchitectureTest.php` as PHPat rules with a `because(...)` explanation
<!-- AGENTS-GENERATED:END patterns -->

<!-- AGENTS-GENERATED:START code-style -->
## Code Style
- Test class name matches source: `MyClass` → `MyClassTest`; same PSR-12/CGL rules as `Classes/`
- Tests are analyzed by PHPStan too (`Build/phpstan.neon` includes `Tests/`) — keep them level-8 clean
- Use data providers for multiple similar cases
- Mock external services — never call the real Universal Messenger API in tests
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START security -->
## Security
- No real API credentials in tests or fixtures — use obviously fake values
- Never weaken or skip a failing test to get CI green; fix the root cause
- Expected exceptions/errors must be asserted, not left as noise in the test output
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR Checklist
- [ ] `composer ci:test:php:unit` and `composer ci:test:php:functional` pass
- [ ] New functionality has tests; regression tests were seen failing on the unfixed code
- [ ] No hardcoded credentials or environment-specific paths
- [ ] PHPStan still clean including the new test files: `composer ci:test:php:phpstan`
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real code in this repo over generic examples.**
> `Unit/Controller/UniversalMessengerControllerTest.php` (unit + testable subclass), `Functional/Configuration/PagesTcaTest.php` (functional TCA check), `Architecture/ArchitectureTest.php` (PHPat rule shape).
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- TYPO3 testing framework: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/Index.html
- PHPat rule reference: https://github.com/carlosas/phpat
- Check `../docs/ARCHITECTURE.md` for the layer rules the architecture tests enforce
- Review root AGENTS.md for project-wide conventions
<!-- AGENTS-GENERATED:END help -->

<!-- AGENTS-GENERATED:START skill-reference -->
## Skill Reference
> For comprehensive TYPO3 testing guidance including fixtures, mocking and CI setup:
> **Invoke skill:** `typo3-testing`
<!-- AGENTS-GENERATED:END skill-reference -->
