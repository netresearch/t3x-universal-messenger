<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-19 -->

# AGENTS.md — workflows

<!-- AGENTS-GENERATED:START overview -->
## Overview
GitHub Actions CI for the extension. Every workflow here is a **thin caller** of a shared reusable workflow from `netresearch/typo3-ci-workflows` or `netresearch/.github` — pins, runners and step logic are maintained centrally, this repo only supplies the matrix and permissions.
<!-- AGENTS-GENERATED:END overview -->

<!-- AGENTS-GENERATED:START filemap -->
## Key Files
| File | Purpose |
|------|---------|
| `ci.yml` | Test matrix (PHP 8.2–8.5 × TYPO3 ^14.0, functional on SQLite, codecov upload) — **intentional drift**, per-extension |
| `checks.yml` | Security/quality jobs (CodeQL, gitleaks, zizmor, fuzz, license, scorecard, dependency-review, pr-quality) + `All security checks` gate — byte-identical across t3x repos, drift-enforced |
| `harness-verify.yml` | Agent-harness consistency check via `Build/Scripts/verify-harness.sh` |
| `check-template-drift.yml` | Fails when shared template files drift from `netresearch/.github` |
| `auto-merge-deps.yml` | Auto-merge for dependency PRs |
| `labeler.yml` / `community.yml` | PR labeling; stale/lock/greetings automation |
| `release.yml` / `republish.yml` | TER release + republish via typo3-ci-workflows reusables |
<!-- AGENTS-GENERATED:END filemap -->

<!-- AGENTS-GENERATED:START golden-samples -->
## Workflow files
- 9 workflows, all `uses:`-callers — no locally implemented job steps except the `gate` job in `checks.yml`
- The extension-specific part is exactly `ci.yml`'s `with:` block; everything else should match the org template (`check-template-drift.yml` enforces this)
<!-- AGENTS-GENERATED:END setup -->

<!-- AGENTS-GENERATED:START structure -->
## Directory structure
```
.github/
  workflows/        → thin reusable-workflow callers (this directory)
  dependabot.yml    → dependency update config
  labeler.yml       → path-based label rules
  template.yaml     → org template-sync manifest
```
No local composite actions, no repo-level PR template (the org-level template from `netresearch/.github` applies).
<!-- AGENTS-GENERATED:END structure -->

<!-- AGENTS-GENERATED:START code-style -->
## Workflow conventions
- **Thin callers only**: change shared behavior upstream in `netresearch/typo3-ci-workflows` / `netresearch/.github`, not here
- **Explicit permissions** on every job; top-level `permissions: {}` or `contents: read` — never rely on defaults
- **Every job added to `checks.yml` MUST also be added to `gate.needs`** — the ruleset requires only `All security checks`, so a job missing from the gate cannot block a merge (see the comment block in `checks.yml`)
- Local `run:` steps (only the gate) pin third-party actions to full commit SHA
- `ci.yml` carries the `# Per-extension test matrix (intentional-drift)` marker — keep it, the drift check reads it
<!-- AGENTS-GENERATED:END code-style -->

<!-- AGENTS-GENERATED:START patterns -->
## Common patterns

### Thin caller (the only pattern used here)
```yaml
jobs:
  ci:
    uses: netresearch/typo3-ci-workflows/.github/workflows/ci.yml@main
    permissions:
      contents: read
    with:
      php-versions: '["8.2","8.3","8.4","8.5"]'
      typo3-versions: '["^14.0"]'
```

### Why individual check names are not required by rulesets
PR-only jobs (`dependency-review`, `pr-quality`) never materialize on `merge_group` refs, and code-scanning checks are posted by the GitHub app, not by these jobs. Rulesets therefore require the stable gate names (`All security checks`, `ci / All CI checks`) — do not add individual job contexts to the ruleset.
<!-- AGENTS-GENERATED:END patterns -->

<!-- AGENTS-GENERATED:START security -->
## Security & safety
- **Never** use `secrets: inherit` — pass secrets explicitly (`ci.yml` passes only `CODECOV_TOKEN`)
- Keep `permissions` minimal per job; the reusables document their required caller contract
- Pin any new third-party action to a full commit SHA with a version comment
- Scheduled weekly runs (`cron: '0 6 * * 1'`) keep CodeQL/scorecard results fresh — do not remove the `schedule` triggers
<!-- AGENTS-GENERATED:END security -->

<!-- AGENTS-GENERATED:START checklist -->
## PR/commit checklist
- [ ] New `checks.yml` job also listed in `gate.needs`
- [ ] Permissions blocks minimal and explicit
- [ ] No `secrets: inherit`; secrets passed explicitly
- [ ] Shared files unchanged (or changed upstream first) — `Template Drift` check stays green
- [ ] Workflow syntax valid (`actionlint` or zizmor will flag issues)
<!-- AGENTS-GENERATED:END checklist -->

<!-- AGENTS-GENERATED:START examples -->
## Patterns to Follow
> **Prefer looking at real code in this repo over generic examples.**
> `ci.yml` (thin caller with matrix), `checks.yml` (gate pattern + extensive in-file rationale comments).
<!-- AGENTS-GENERATED:END examples -->

<!-- AGENTS-GENERATED:START help -->
## When stuck
- Shared reusables: https://github.com/netresearch/typo3-ci-workflows and https://github.com/netresearch/.github
- GitHub Actions docs: https://docs.github.com/en/actions
- Read the comment blocks in `checks.yml` — they document the gate/merge-queue pitfalls
- Review root AGENTS.md for project-wide conventions
<!-- AGENTS-GENERATED:END help -->
