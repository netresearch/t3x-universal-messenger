<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — Resources

<!-- AGENTS-GENERATED:START overview -->
## Overview
Fluid templates, XLIFF translations and static assets of the `universal_messenger` extension: backend module views, newsletter content-element/page templates and the backend module stylesheet.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Setup & environment
```
Private/Language/                → XLIFF translations (locallang*.xlf, Backend.xlf + de.* counterparts)
Private/Backend/                 → Backend module Layouts/ and Templates/
Private/Templates/               → Frontend Fluid: Backend/, ContentElements/, Page/, ViewHelpers/
Private/Layouts/                 → Shared Fluid layouts
Private/FluidStyledMailContent/  → Mail-optimized content element templates
Public/Icons/                    → Extension and module icons
Public/Css/                      → Module.css (registered in ext_localconf.php as BE stylesheet)
```
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START types -->
## Common patterns
- Newsletter HTML is rendered from `Private/Templates/` + `Private/FluidStyledMailContent/` and then post-processed by the frontend middlewares (`Classes/Middleware/`) — CSS inlining happens there, not in the templates
- Custom ViewHelpers used in these templates live in `Classes/ViewHelpers/` (`Condition/`, `Format/`, `Html/`)
- Every user-facing string goes through XLIFF: default-language file plus `de.`-prefixed German counterpart in `Private/Language/`
<!-- AGENTS-GENERATED:END types -->

<!-- AGENTS-GENERATED:START code-style -->
## Code style & conventions
- Fluid: UpperCamelCase template names matching controller actions; keep logic in ViewHelpers/controllers, not in templates
- XLIFF: keep `trans-unit` IDs stable — they are referenced from PHP and Fluid; add new units to the default file first, then translate in the `de.` file
- Mail templates must stay email-client-safe: table-based layout helpers from `Classes/ViewHelpers/Html/` (Row/Column/Container/Spacer), no external CSS assumptions
- CSS: `Public/Css/Module.css` styles the backend module only
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START security -->
## Security & safety
- Never store secrets or API credentials in templates, translation files or CSS
- Fluid `{variable}` auto-escapes — use `<f:format.raw>` only for content sanitized upstream (the middleware pipeline expects escaped template output)
- Universal Messenger placeholders are curly-brace-wrapped; `DecodeCurlyBracesMiddleware` re-decodes braces that the CSS-inlining DOM step encoded in URLs — do not work around encoding issues inside templates
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR/commit checklist
- [ ] New strings exist in the default XLIFF file and the `de.` counterpart
- [ ] Template changes verified in the rendered newsletter preview, not only in the backend module
- [ ] No secrets or environment-specific URLs in resources
- [ ] XLIFF/XML files are well-formed (`composer ci:test:php:lint` does not cover XML — validate manually)
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real code in this repo over generic examples.**
> `Private/Language/locallang.xlf` + `Private/Language/de.locallang.xlf` (translation pairing), `Private/FluidStyledMailContent/` (mail-safe markup).
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- Fluid reference: https://docs.typo3.org/other/typo3fluid/fluid/main/en-us/
- XLIFF in TYPO3: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Localization/Index.html
- Check how templates are consumed in `Classes/Service/NewsletterRenderService.php` and the middlewares
- Review root AGENTS.md for project-wide conventions
<!-- AGENTS-GENERATED:END help -->
