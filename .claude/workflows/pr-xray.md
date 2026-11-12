# PR Xray Workflow

Perform an evidence-based, multi-reviewer examination of one pull request or branch and package the results for download.

## Phase 1 — Intake and baseline

1. Read `AGENTS.md` and `.agents/skills/pr-xray/SKILL.md`.
2. Resolve the repository, PR/branch, intended base, and exact base/head commits.
3. Read the PR body, linked issue, requirements, and material discussion.
4. Use `.agents/agents/pr-xray-coordinator.md` to produce the intent contract, diff inventory, risk assessment, and reviewer-routing plan.

Do not continue against an ambiguous commit range without recording the limitation or obtaining clarification.

## Phase 2 — Parallel domain review

Apply `.agents/references/pr-xray-reviewer-routing.md`. Dispatch applicable read-only reviewers:

- `.agents/agents/ajax-controller-reviewer.md`
- `.agents/agents/db-migration-reviewer.md`
- `.agents/agents/generation-pipeline-reviewer.md`
- `.agents/agents/background-job-reviewer.md`
- `.agents/agents/l10n-reviewer.md`
- `.agents/agents/bootstrap-dependency-reviewer.md`
- `.agents/agents/qa-reviewer.md` (always)

Every reviewer returns the envelope in `.agents/references/pr-xray-finding-contract.md`. Domain reviewers do not write artifacts or modify the reviewed branch.

## Phase 3 — Focused verification

Select checks proportional to the changed code and follow `TESTING.md` exactly. Record commands, results, environment ownership, and unavailable checks. Never imply that an unexecuted test passed.

## Phase 4 — Adjudication

Give the coordinator output, reviewer envelopes, and verification results to `.agents/agents/pr-xray-adjudicator.md`.

The adjudicator validates evidence, merges duplicates, resolves contradictions, checks changed-file and acceptance-criteria coverage, dispositions every finding, and assigns the final verdict.

## Phase 5 — Report assembly

Give only adjudicated material to `.agents/agents/pr-xray-report-assembler.md`. Follow `.agents/references/pr-xray-report-format.md`.

The assembler writes individual Markdown reports, `findings.json`, `manifest.json`, and a validated ZIP under `.artifacts/pr-xray/<safe-run-id>/`.

## Phase 6 — Handoff

Return:

- final verdict
- blocking findings
- important non-blocking findings
- verification executed and unavailable
- report directory
- ZIP archive path

PR Xray reports readiness; it does not merge or implement fixes without separate authorization.
