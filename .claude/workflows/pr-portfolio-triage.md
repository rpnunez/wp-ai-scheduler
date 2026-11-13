# PR Portfolio Triage Workflow

Rank and route multiple open pull requests. This workflow prioritizes work; it does not deeply review each change set.

## Phase 1 — Define the portfolio

1. Resolve repository and default branch.
2. Collect the requested open PR set and record the query timestamp.
3. Gather head/base refs, draft state, checks, conflicts, approvals, labels, linked issues, activity, and changed areas.
4. Record unavailable fields explicitly.

## Phase 2 — Compare work

Use `.claude/agents/pr-portfolio-analyst.md` to identify duplicates, partial overlaps, dependencies, and superseded work by intent, acceptance criteria, and changed areas.

## Phase 3 — Rank and route

Rank by value, readiness, review effort, risk, blocker cost, and dependency order. Identify:

- quick wins
- blocked or stale work
- missing labels or reviews
- recommended review and merge order
- individual PRs requiring `.claude/skills/pr-xray/SKILL.md`

## Phase 4 — Report

Produce a timestamped table plus concise overlap groups, blockers, remediation, ordering rationale, and PR Xray recommendations. State that portfolio ranking is not merge approval.
