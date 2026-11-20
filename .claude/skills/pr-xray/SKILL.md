---
name: pr-xray
description: Perform an evidence-based, multi-reviewer deep examination of one wp-ai-scheduler pull request or branch, adjudicate findings, and package individual reports plus a ZIP archive.
---

# PR Xray

Use this skill for a deep review of one PR or branch against its intended base, especially for large refactors, risky cross-cutting changes, or pre-merge verification.

## Required inputs

- PR URL/number or branch
- intended base branch
- PR body, linked issue, or supplied requirements when available
- repository instructions, beginning with `AGENTS.md`

Resolve and record exact base and head commits before reviewing. If the local branch differs from the PR head, stop or clearly review the intended commit range.

## Workflow

Follow `.claude/workflows/pr-xray.md`:

1. Establish intent and acceptance criteria.
2. Inventory the complete diff and risk.
3. Route applicable domain reviewers.
4. Run focused verification without modifying source code.
5. Adjudicate evidence, contradictions, duplicates, and coverage.
6. Assemble individual reports, JSON, a manifest, and a validated ZIP.

## Evidence requirements

- All reviewers use `.claude/references/pr-xray-finding-contract.md`.
- Severity, confidence, status, and verdicts use `.claude/references/pr-xray-severity-model.md`.
- Reviewer selection uses `.claude/references/pr-xray-reviewer-routing.md`.
- Every changed file is owned or explicitly excluded.
- Every acceptance criterion receives a verification status.
- Reports distinguish inspected, executed, inferred, unavailable, and failed verification.

## Non-goals

- Do not implement fixes unless the user separately requests them.
- Do not mutate the reviewed branch during review.
- Do not treat a clean static review as proof that runtime behavior passed.
- Do not use PR Xray to rank a portfolio of open PRs; use `pr-portfolio-triage`.

## Output

Use `.claude/references/pr-xray-report-format.md`. Return the verdict, blocking and non-blocking findings, verification performed and missing, plus absolute paths to the report directory and validated ZIP.
