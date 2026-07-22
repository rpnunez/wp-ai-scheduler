---
name: aips-pr-prep
description: Use before opening or updating a pull request against the AI Post Scheduler repo — determining which risk labels apply, what verification to run, and whether docs need updating, per .github/pull_request_template.md.
---

# PR prep workflow

`.github/pull_request_template.md` defines the checklist reviewers expect. Work
through it before a PR is opened, rather than leaving it for CI/review to catch.

## Verification

- Run `composer test` (or a targeted `vendor/bin/phpunit tests/<file>.php`) for the
  changed behavior — but only when explicitly asked or the task requires it, per the
  repo's testing policy (AGENTS.md/CLAUDE.md). If not run, say so explicitly in the
  PR/response rather than silently skipping it.
- Run `cd ai-post-scheduler && composer lint:repository-boundary` if any
  controller/service file changed.
- Manually verify changed admin/runtime paths where practical.
- Update documentation if behavior or workflow changed (`docs/AI_AGENT_REFERENCE.md`,
  `docs/DEVELOPMENT_GUIDELINES.md`, `CLAUDE.md`/`AGENTS.md` as appropriate — don't
  duplicate content across them).

## Risk labels — derive from touched files

| Files touched | Label(s) |
|---|---|
| `class-aips-db-manager.php`, `class-aips-db-migrations.php`, `Version:`/`AIPS_VERSION` | `schema-change` |
| `templates/admin/*.php`, `assets/js/*.js`, admin CSS | `admin-ui` + `needs-browser-test` |
| `class-aips-ajax-registry.php`, any `*-controller.php` | `ajax-registry` |
| `class-aips-bulk-batch-processor.php`, `class-aips-bulk-batch-job-store.php`, scheduler/cron classes | `cron` |
| `class-aips-generation-context*.php`, `class-aips-generator.php`, prompt builders | `generation-pipeline` |
| Nonce/capability checks, auth, data sanitization/escaping paths | `security-sensitive` |
| Any change that duplicates existing logic/tables/endpoints | `duplicate-risk` (must be addressed before merge, not just labeled) |

## Guardrails

- This skill only determines labels/checklist status — actually applying GitHub
  labels and opening the PR follows the normal PR-creation flow (draft PR, template
  sections filled from the diff).
- Don't skip the risk-checklist reasoning just because a change looks small —
  e.g. a one-line schema tweak still needs the version bump and `schema-change`
  label.

## Reference files

- `.github/pull_request_template.md`
