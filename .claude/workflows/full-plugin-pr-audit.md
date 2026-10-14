# Full Plugin PR Audit Workflow

Master orchestration workflow that fans out multiple specialized subagents (`qa-reviewer`, `ajax-controller-reviewer`, `db-migration-reviewer`, `generation-pipeline-reviewer`, `batch-job-reviewer`, `l10n-reviewer`) to evaluate a pull request or major feature slice for release readiness.

## Purpose

Provide a comprehensive, multi-perspective security, architecture, quality, and performance review before merging code into `wp-ai-scheduler`.

## Phases

### Phase 1 — PR Triage & Change Scoping
Spawn `.claude/agents/pr-triage.md` to:
1. Identify all files modified, added, or deleted in the pull request / working tree.
2. Categorize risk (Low / Medium / High).
3. Assign target domain review agents based on touched subsystems.

### Phase 2 — Parallel Domain Reviews
Concurrently dispatch specialized agents for touched domains:
- **AJAX Security Reviewer** (`.claude/agents/ajax-controller-reviewer.md`) if `class-aips-*-controller.php` or `class-aips-ajax-registry.php` changed.
- **DB Migration Reviewer** (`.claude/agents/db-migration-reviewer.md`) if `class-aips-db-*.php` or repository files changed.
- **Generation Pipeline Reviewer** (`.claude/agents/generation-pipeline-reviewer.md`) if context, prompt builder, or generator files changed.
- **Batch Job Reviewer** (`.claude/agents/batch-job-reviewer.md`) if cron or batch processor files changed.
- **L10n Reviewer** (`.claude/agents/l10n-reviewer.md`) if templates, options, or UI text changed.
- **QA Reviewer** (`.claude/agents/qa-reviewer.md`) to evaluate test plan completeness and edge cases.

### Phase 3 — Repository Boundary Linting & Static Checks
Execute repository boundary lint check:
```bash
cd ai-post-scheduler && composer lint:repository-boundary
```

### Phase 4 — Final PR Readiness Synthesis
Consolidate findings into a unified PR Review Decision:
- **Overall Verdict**: Approved / Needs Changes / Rejected
- **Blocking Security & Architectural Issues**: (Must fix before merge)
- **Non-blocking Recommendations & Polish**: (Can fix in follow-up)
- **Verified Checklist**: Nonce checks, Capability checks, Repository boundaries, Escaping, Context generation, Test coverage.

## Useful references
- `AGENTS.md` — canonical rules
- `CLAUDE.md` — commands and architecture mapping
