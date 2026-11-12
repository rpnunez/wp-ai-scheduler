# PR Portfolio Triage and PR Xray Implementation Plan

> **For agentic workers:** Implement the approved tasks in order, keep domain reviewers read-only, and verify every cross-reference before opening the PR.

**Goal:** Separate portfolio prioritization from deep PR review and add evidence contracts, adjudication, updated domain reviewers, and downloadable report packages.

**Architecture:** PR Portfolio Triage handles multiple-PR ranking. PR Xray coordinates one change set through domain review, verification, adjudication, and centralized artifact assembly.

**Spec:** `.claude/docs/2026-11-11-pr-xray-design.md`

## Global constraints

- Canonical capability files live under `.claude/`.
- `AGENTS.md` remains authoritative for project policy.
- No plugin runtime, schema, setting, AJAX, cron, or generation behavior changes.
- Do not modify `.codex` or `.github`.
- Only the report assembler writes PR Xray artifacts.

## Tasks

- [x] Add shared finding, severity, routing, and report contracts.
- [x] Add report templates.
- [x] Split PR Portfolio Triage from the former PR Triage capability.
- [x] Add the PR Xray skill, coordinator, and workflow.
- [x] Realign AJAX, database, generation, background-job, localization, and QA reviewers.
- [x] Add bootstrap/dependency review.
- [x] Add cross-reviewer adjudication.
- [x] Add centralized report assembly and ZIP validation.
- [x] Align existing subsystem sweep workflows.
- [x] Update discovery documentation and renamed references.
- [x] Run structural, scope, contract, packaging, and workflow dry-run checks.
- [ ] Open the PR, perform a full branch review, resolve findings, and update the PR.

## Verification

- `git diff --check`
- Validate agent front matter, unique names, local links, routing targets, and permission boundaries.
- Confirm active references do not use retired paths.
- Confirm the diff is limited to `.claude/**` and `CLAUDE.md`.
- Exercise representative accepted, merged, rejected, and verification-blocked findings.
- Build and inspect a temporary report ZIP, then remove the fixture.
- Perform a PR Xray workflow dry run and record limitations.
