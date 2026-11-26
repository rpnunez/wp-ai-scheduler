# AJAX Security Sweep Workflow

Audit every registered AJAX action and controller across the plugin. This is a codebase-wide subsystem sweep; use `.claude/workflows/pr-xray.md` for one change set.

## Phase 1 — Discovery

1. Read `AIPS_Ajax_Registry::$map` and enumerate every action/controller pair.
2. Discover controller files and flag registered classes or files missing from either inventory.
3. Map request producers and response consumers where discoverable.

## Phase 2 — Parallel action-path review

Assign each action path to `.claude/agents/ajax-controller-reviewer.md`. Review registry routing, nonce/capability behavior, typed input parsing, response compatibility, boundaries, failure disclosure, and tests.

Every reviewer returns `.claude/references/pr-xray-finding-contract.md`; do not use an alternate pass/fail schema.

## Phase 3 — Cross-check

- Confirm every registered action was reviewed.
- Compare inconsistent interpretations of shared patterns.
- Independently spot-check high-risk state-changing actions.
- Record unregistered hooks, orphaned controllers, and coverage gaps.

## Phase 4 — Report

Use the shared severity/confidence model. Produce an action-by-requirement summary, adjudicated findings with file/line evidence, uncovered actions, and prioritized remediation. Use the PR Xray report assembler only when downloadable artifacts are requested.

## References

- `AGENTS.md`
- `.claude/agents/ajax-controller-reviewer.md`
- `.claude/references/pr-xray-finding-contract.md`
- `.claude/references/pr-xray-severity-model.md`
