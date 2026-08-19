# Architecture

Agent-facing component map of the `universal_messenger` TYPO3 extension. Every path below exists in this repo; the dependency rules mirror `Tests/Architecture/ArchitectureTest.php` (PHPat, executed inside the PHPStan run — `composer ci:test:php:phpstan`).

## System overview

The extension adds a TYPO3 v14 backend module for sending newsletters through the Universal Messenger (UM) API. Newsletter channels are imported from the UM API into a local table via a CLI command; the backend module renders a newsletter page to standalone HTML (frontend request, post-processed by two PSR-15 middlewares) and hands it to the UM API for test or live dispatch. All API traffic goes through `netresearch/sdk-api-universal-messenger`.

## Components

| Component | Path | Role |
|-----------|------|------|
| Backend module controller | `Classes/Controller/UniversalMessengerController.php` (+ `AbstractBaseController.php`) | Channel selection, preview, test/live send |
| Preview plugin controller | `Classes/Controller/NewsletterPreviewController.php` | Extbase frontend plugin rendering the preview (`preview` action) |
| API service | `Classes/Service/UniversalMessengerService.php` | Singleton wrapper constructing the SDK client from `Classes/WebserviceConfiguration.php` |
| Render service | `Classes/Service/NewsletterRenderService.php` | Renders newsletter pages to HTML via `RequestFactory` HTTP calls |
| Infrastructure repositories | `Classes/Repository/` (`AbstractRepository`, `NewsletterRepository`, `EventFileRepository`) | UM-API-backed data access; depend on `UniversalMessengerService->api()` |
| Domain layer | `Classes/Domain/Model/NewsletterChannel.php`, `Classes/Domain/Repository/NewsletterChannelRepository.php` | Extbase model/repository for the local channel table (`ext_tables.sql`, TCA in `Configuration/TCA/`) |
| CLI command | `Classes/Command/ImportCommand.php` | `universal-messenger:newsletter-channels:import` (schedulable), syncs channels API → DB |
| Frontend middlewares | `Classes/Middleware/InlineCssMiddleware.php`, `Classes/Middleware/DecodeCurlyBracesMiddleware.php` | CSS inlining (Emogrifier) and curly-brace decoding of the rendered newsletter response; ordered in `Configuration/RequestMiddlewares.php` |
| Backend event listeners | `Classes/Backend/EventListener/` | Page-layout content, content-preview rendering, blinded-configuration options (tags in `Configuration/Services.yaml`) |
| Data processing | `Classes/DataProcessing/ControlStructureProcessor.php` | FlexForm-driven data processor for the control-structure content element |
| ViewHelpers | `Classes/ViewHelpers/` (`Condition/`, `Format/`, `Html/`) | Newsletter/mail-safe Fluid helpers (row/column/container/spacer/body) |
| Configuration access | `Classes/Configuration.php`, `Classes/WebserviceConfiguration.php`, `Classes/Constants.php` | Typed access to extension settings (`ext_conf_template.txt`) |

## Dependency rules (enforced by PHPat)

From `Tests/Architecture/ArchitectureTest.php` — violations fail `composer ci:test:php:phpstan`:

1. `ViewHelpers`, `Service`, `Backend\EventListener`, `Middleware`, `Command`, `Repository`, `DataProcessing` must **not** depend on `Controller` (controllers are the outermost layer).
2. `Service` must **not** depend on `Repository` — the infrastructure repository layer depends on services, and the direction must stay acyclic.
3. `Domain` is the innermost layer: it must **not** depend on `Controller`, `Service`, `Middleware`, `Command`, `Backend`, `ViewHelpers`, `Repository` or `DataProcessing`.

## Data flow

- **Channel import**: `ImportCommand` → `NewsletterRepository` (UM API via `UniversalMessengerService->api()`) → `NewsletterChannelRepository` persists `NewsletterChannel` rows.
- **Newsletter send**: backend module (`UniversalMessengerController`) → `NewsletterRenderService` performs a frontend HTTP request → the response is post-processed by `InlineCssMiddleware`, then `DecodeCurlyBracesMiddleware` re-decodes the braces the CSS-inlining DOM step encoded → controller submits the final HTML through `NewsletterRepository` to the UM API.
- **Preview**: same render path, exposed as the `NewsletterPreview` Extbase plugin (`ext_localconf.php`); preview parameters are excluded from cHash.

## Key decisions

- No ADR directory exists; design rationale lives in code comments (`ext_localconf.php` on TypoScript/site-set loading and the v14.2 page-type behavior, `checks.yml` on the CI gate pattern) and in `README.md`.
- Version/compatibility facts: `ext_emconf.php` and `composer.json` are the source of truth (PHP ^8.2, TYPO3 ^14.0).
