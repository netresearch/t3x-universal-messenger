<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — Classes

<!-- AGENTS-GENERATED:START overview -->
## Overview
PHP code of the `universal_messenger` TYPO3 v14 extension: a backend module (Extbase controller) for sending newsletters through the Universal Messenger API, plus frontend middlewares, an import CLI command and backend event listeners. The API is accessed exclusively through `netresearch/sdk-api-universal-messenger` (`Netresearch\Sdk\UniversalMessenger\*`).
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `Controller/UniversalMessengerController.php` | Backend module controller (newsletter selection/sending) |
| `Controller/NewsletterPreviewController.php` | Extbase frontend plugin rendering the newsletter preview |
| `Service/UniversalMessengerService.php` | Wraps the SDK client; configured from extension settings |
| `Service/NewsletterRenderService.php` | Renders newsletter pages to HTML via HTTP requests |
| `Repository/NewsletterRepository.php` | Infrastructure repository talking to the UM API (via services) |
| `Domain/Repository/NewsletterChannelRepository.php` | Extbase repository for imported channels (DB) |
| `Command/ImportCommand.php` | CLI `universal-messenger:newsletter-channels:import` (schedulable) |
| `Middleware/InlineCssMiddleware.php` + `Middleware/DecodeCurlyBracesMiddleware.php` | Frontend PSR-15 middlewares post-processing newsletter HTML (see `Configuration/RequestMiddlewares.php`) |
<!-- AGENTS-GENERATED:END filemap -->

<!-- AGENTS-GENERATED:START golden-samples -->
## Golden Samples (follow these patterns)
| Pattern | Reference |
|---------|-----------|
| Backend controller | `Controller/UniversalMessengerController.php` |
| Service (SDK wrapper, singleton) | `Service/UniversalMessengerService.php` |
| Event listener (tagged in Services.yaml) | `Backend/EventListener/PageContentPreviewRenderingEventListener.php` |
| ViewHelper | `ViewHelpers/Format/PlaceholderViewHelper.php` |
<!-- AGENTS-GENERATED:END golden-samples -->

<!-- AGENTS-GENERATED:START setup -->
## Setup & environment
- Install dev tools: `composer install` (binaries in `.Build/bin/`, vendors in `.Build/vendor/`)
- PHP: ^8.2 · TYPO3: ^14.0 (core, backend, frontend, extbase, fluid, lowlevel)
- Services are autowired/autoconfigured via `Configuration/Services.yaml`; `Domain/Model/*` is excluded from DI
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START structure -->
## Directory structure
```
Classes/
  Controller/      → Backend module + preview plugin controllers
  Service/         → Business logic (SDK access, newsletter rendering)
  Repository/      → Infrastructure repositories (UM API-backed, extend AbstractRepository)
  Domain/          → Model/ (NewsletterChannel) + Repository/ (Extbase, DB-backed)
  Middleware/      → Frontend PSR-15 middlewares
  Command/         → Symfony console commands
  Backend/         → EventListener/ for backend events
  DataProcessing/  → ControlStructureProcessor (FlexForm data processor)
  ViewHelpers/     → Fluid ViewHelpers (Condition/, Format/, Html/)
```
Layer rules (enforced by PHPat, see `../docs/ARCHITECTURE.md`): nothing depends on controllers; services must not depend on repositories; `Domain/` depends on no other layer.
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START commands -->
## Build & tests
| Task | Command |
|------|---------|
| Lint | `composer ci:test:php:lint` |
| Code style check / fix | `composer ci:test:php:cgl` / `composer ci:cgl` |
| PHPStan (level 8 + PHPat) | `composer ci:test:php:phpstan` |
| Rector / Fractor dry-run | `composer ci:test:php:rector` / `composer ci:test:php:fractor` |
| Unit / Functional tests | `composer ci:test:php:unit` / `composer ci:test:php:functional` |
| Everything except functional | `composer ci:test` |
<!-- AGENTS-GENERATED:END commands -->

<!-- AGENTS-GENERATED:START code-style -->
## Code style & conventions
- **PSR-12** + TYPO3 CGL, enforced by `php-cs-fixer` (`Build/.php-cs-fixer.dist.php`)
- Strict types: `declare(strict_types=1);` in all PHP files; license header block on top
- Namespace: `Netresearch\UniversalMessenger\` (PSR-4 from `Classes/`)
- Constructor dependency injection via `Configuration/Services.yaml` autowiring; `GeneralUtility::makeInstance()` only where DI is unavailable (e.g. inside commands at runtime)
- Services implement `SingletonInterface` where stateless
- New PHPStan findings: fix them — regenerate the baseline (`composer ci:test:php:phpstan:baseline`) only for pre-existing debt

### Naming conventions
| Type | Convention | Example |
|------|------------|---------|
| Extension key | `lowercase_underscore` | `universal_messenger` |
| Composer name | `vendor/ext-key` | `netresearch/universal-messenger` |
| Namespace | `Vendor\ExtKey\` | `Netresearch\UniversalMessenger\` |
| Controller | `*Controller` | `NewsletterPreviewController` |
| Repository | `*Repository` | `NewsletterChannelRepository` |
| ViewHelper | `*ViewHelper` | `PlaceholderViewHelper` |
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START security -->
## Security & safety
- API credentials (base URL, key, secret) come from the extension configuration (`ext_conf_template.txt`) — never hard-code or log them
- **Escape output** in Fluid: `{variable}` auto-escapes; `<f:format.raw>` only for content that is sanitized upstream
- Use QueryBuilder/Extbase repositories — never raw SQL
- Backend access is permission-gated (`be_groups`/`be_users` TCA overrides define newsletter channel access); keep access checks when touching the module
- Middlewares post-process frontend responses — validate assumptions about content type before rewriting the body
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR/commit checklist
- [ ] `composer ci:test` passes (lint, phpstan, rector, fractor, unit, cgl)
- [ ] Functional tests pass if controllers/TCA/DB code changed: `composer ci:test:php:functional`
- [ ] No new PHPat layer violations (part of the phpstan run)
- [ ] TCA changes have matching SQL in `ext_tables.sql`
- [ ] New services registered/autowirable via `Configuration/Services.yaml`
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real code in this repo over generic examples.**
> See **Golden Samples** section above for files that demonstrate correct patterns.
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- TYPO3 Core API: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/
- TCA Reference: https://docs.typo3.org/m/typo3/reference-tca/main/en-us/
- SDK source: `.Build/vendor/netresearch/sdk-api-universal-messenger/` (after `composer install`)
- Check `../docs/ARCHITECTURE.md` for the component map and layer rules
- Review root AGENTS.md for project-wide conventions
<!-- AGENTS-GENERATED:END help -->

<!-- AGENTS-GENERATED:START skill-reference -->
## Skill Reference
> For TYPO3 extension standards, TER compliance, and conformance checks:
> **Invoke skill:** `typo3-conformance`
<!-- AGENTS-GENERATED:END skill-reference -->
