---
name: pr-portfolio-triage
description: Rank and route multiple open wp-ai-scheduler pull requests by readiness, overlap, risk, blockers, and recommended merge order. Use for portfolio-level PR prioritization, not deep review of one change set.
---

# PR Portfolio Triage

Use this skill when the user asks which open PRs to review or merge first, requests quick wins or blocker analysis, or wants duplicate and overlap detection across multiple PRs.

## Scope

- Collect current PR metadata, checks, conflicts, approvals, activity, labels, linked issues, and changed areas.
- Detect duplicate, partially overlapping, dependent, and superseded work.
- Rank PRs by readiness, value, review effort, and risk.
- Recommend review and merge order.
- Escalate an individual high-risk or ambiguous PR to `pr-xray`.

## Non-goals

- Do not perform a comprehensive code review of every PR.
- Do not claim runtime correctness from metadata or a shallow diff scan.
- Do not use a portfolio ranking as merge approval.

## Required workflow

Follow `.claude/workflows/pr-portfolio-triage.md` and use `.claude/agents/pr-portfolio-analyst.md` for the portfolio assessment.

## Output

Return a timestamped ranked queue containing:

- readiness and risk
- quick wins
- blockers and concrete remediation
- overlap/dependency groups
- recommended review and merge order
- PRs recommended for PR Xray

## References

- `AGENTS.md`
- `docs/DEVELOPMENT_GUIDELINES.md`
- `.claude/skills/pr-xray/SKILL.md`
