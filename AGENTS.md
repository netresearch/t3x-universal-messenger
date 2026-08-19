<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-08-19 | Last verified: 2026-08-19 -->

# AGENTS.md

**Precedence:** the **closest `AGENTS.md`** to the files you're changing wins. Root holds global defaults only.

TYPO3 v14 backend extension (`universal_messenger`) for sending newsletters through the Universal Messenger API via `netresearch/sdk-api-universal-messenger`. Extension version: see `ext_emconf.php`.

## Commands
> Source: composer.json scripts + Makefile (verified 2026-08-19). Run `composer install` first — binaries land in `.build/bin/` (composer `bin-dir`), vendors in `.build/vendor/`.

<!-- AGENTS-GENERATED:START commands -->
| Task | Command |
|------|---------|
| Lint (PHP syntax) | `composer ci:test:php:lint` |
| Static analysis (PHPStan level 8 + PHPat) | `composer ci:test:php:phpstan` (or `make phpstan`) |
| Code style check | `composer ci:test:php:cgl` (or `make cgl`) |
| Code style fix | `composer ci:cgl` (or `make cgl-fix`) |
| Rector dry-run | `composer ci:test:php:rector` (or `make rector`) |
| Fractor dry-run | `composer ci:test:php:fractor` |
| Unit tests | `composer ci:test:php:unit` |
| Functional tests (needs DB; CI uses SQLite) | `composer ci:test:php:functional` |
| All checks except functional | `composer ci:test` |
<!-- AGENTS-GENERATED:END commands -->

## Response Style
- Answer first, elaborate only if needed. No sycophantic openers ("Great question!", "Absolutely!").
- For yes/no or status questions, lead with the answer.
- Skip preamble. Match response length to task complexity.

## Workflow
1. **Before coding**: Read nearest `AGENTS.md` + check Golden Samples for the area you're touching
2. **After each change**: Run the smallest relevant check (lint → phpstan → single test)
3. **Before committing**: Run full test suite if changes affect >2 files or touch shared code
4. **Before claiming done**: Run verification and **show output as evidence** — never say "try again", "should work now", "tested", "verified", or "all green" without pasted command output in the same turn

## File Map
<!-- AGENTS-GENERATED:START filemap -->
```
Classes/         → PHP code (PSR-4: Netresearch\UniversalMessenger\)
Configuration/   → TYPO3 config: TCA, Services.yaml, Sets/, TypoScript, FlexForms, Backend module
Resources/       → Fluid templates, XLF translations, icons, CSS
Documentation/   → README screenshots (PNG only — no RST manual)
Tests/           → Unit/, Functional/, Architecture/ (PHPat) suites
Build/           → tool configs (phpstan, rector, fractor, PHPUnit XML) + Scripts/
.github/         → CI workflows (thin callers of shared reusables)
```
<!-- AGENTS-GENERATED:END filemap -->

Architecture: component map and dependency rules in `docs/ARCHITECTURE.md` — the rules are enforced by `Tests/Architecture/ArchitectureTest.php` (PHPat, runs inside PHPStan). Execution plans: `docs/exec-plans/`.

## Golden Samples (follow these patterns)
<!-- AGENTS-GENERATED:START golden-samples -->
| For | Reference | Key patterns |
|-----|-----------|--------------|
| Backend controller | `Classes/Controller/UniversalMessengerController.php` | extends `AbstractBaseController`, ModuleTemplate, DI |
| Service | `Classes/Service/UniversalMessengerService.php` | SDK wrapper, `SingletonInterface`, constructor DI |
| Unit test | `Tests/Unit/Controller/UniversalMessengerControllerTest.php` | UnitTestCase, testable subclass |
| Functional test | `Tests/Functional/Controller/UniversalMessengerControllerArgumentsTest.php` | FunctionalTestCase |
<!-- AGENTS-GENERATED:END golden-samples -->

## Heuristics (quick decisions)
<!-- AGENTS-GENERATED:START heuristics -->
| When | Do |
|------|-----|
| Adding class | Follow PSR-4 under `Classes/`; respect layer rules in `docs/ARCHITECTURE.md` |
| Adding controller | Create in `Classes/Controller/`, register in `Configuration/Services.yaml` if backend |
| Adding service | Create in `Classes/Service/` — services must not depend on controllers or repositories (PHPat) |
| Running tasks | Check `make help` and `composer.json` scripts |
| Committing | Conventional Commits + `git commit -S --signoff` (signature AND DCO both enforced) |
| Adding dependency | Ask first - we minimize deps |
| Unsure about pattern | Check Golden Samples above |
<!-- AGENTS-GENERATED:END heuristics -->

## Repository Settings
<!-- AGENTS-GENERATED:START repo-settings -->
- **Default branch:** `main`
- **Merge strategy:** merge commits only (squash/rebase disabled)
- **Signed commits:** required
- **Required checks (rulesets):** `All security checks`, `CodeQL`, `DCO`, `Opengrep OSS`, `betterleaks`, `ci / All CI checks`, `scorecard`, `zizmor`
- **Active rulesets:** require-signed-commits, t3x-baseline, t3x-pull-request
<!-- AGENTS-GENERATED:END repo-settings -->

## Boundaries

### Always Do
- Run code style + PHPStan before committing
- Add tests for new code paths
- Use conventional commit format: `type(scope): subject`
- Use **atomic commits** (one logical change per commit); preserve signatures, keep bisection useful
- **Show test output as evidence before claiming work is complete** — never say "try again", "should work now", "tested", "verified", or "all green" without pasted command output
- Before any edit, verify `pwd` resolves inside the intended repo worktree — not `.bare/`, not `~/.claude/skills/…`, not `~/.claude/plugins/cache/…` (those are read-only caches that get clobbered on update)
- For upstream dependency fixes: run **full** test suite, not just affected tests
- Force-push only with `--force-with-lease`
- Follow PSR-12 / TYPO3 CGL and PHP ^8.2 features

### Ask First
- Adding new dependencies
- Modifying CI/CD configuration
- Changing public API signatures
- Repo-wide refactoring or rewrites
- Operations that touch >3 repos (produce a dry-run plan first)

### Never Do
- Commit secrets, credentials, or sensitive data
- Modify `.build/` or other generated files
- Push directly to `main` — open a PR
- Merge a PR before all review threads are resolved
- Squash commits during merge or rebase unless the user explicitly asked
- Edit installed skill/plugin cache paths (`~/.claude/skills/`, `~/.claude/plugins/cache/`, `**/.bare/**`) — always the source worktree
- Reply to review comments with bare "Addressed" or "Fixed" — cite the resolving commit SHA
- Commit a `composer.lock` — TYPO3 extension repos ship without a lockfile
- Change `ext_tables.sql` without matching TCA changes (and vice versa)

## Contributing (for AI agents)
- **Comprehension**: Understand the problem before submitting code. Read the linked issue, understand *why* the change is needed, not just *what* to change.
- **Context**: Every PR must explain the trade-offs considered and link to the issue it addresses. Disclose AI assistance if the project requires it.
- **Continuity**: Respond to review feedback. Drive-by PRs without follow-up will be closed.

## Scoped AGENTS.md (MUST read when working in these directories)
<!-- AGENTS-GENERATED:START scope-index -->
- `./Classes/AGENTS.md` — extension PHP code: controllers, services, middlewares, SDK integration
- `./Tests/AGENTS.md` — unit, functional and architecture (PHPat) test suites
- `./Resources/AGENTS.md` — Fluid templates, XLF translations, static assets
- `./.github/workflows/AGENTS.md` — CI workflows: thin callers of shared reusables
<!-- AGENTS-GENERATED:END scope-index -->

> **Agents**: When you read or edit files in a listed directory, you **must** load its AGENTS.md first. It contains directory-specific conventions that override this root file.

## When instructions conflict
The nearest `AGENTS.md` wins. Explicit user prompts override files.
