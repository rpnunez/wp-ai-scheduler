# Generation Pipeline Sweep Workflow

Audit generation semantics across all plugin entry points. This is a codebase-wide subsystem sweep; use PR Xray for one change set.

## Phase 1 — Map the pipeline

Inventory generation contexts/factories, prompt builders, initial and regeneration entry points, scheduled/bulk paths, provider execution, response parsing, partial state, recovery, history, logging, and correlation.

## Phase 2 — Parallel path review

Use `.agents/agents/generation-pipeline-reviewer.md` to trace each entry point end to end. Compare context fields, prompt semantics, provider options, output/status effects, recovery, and tests across paths.

Every reviewer returns the shared PR Xray finding envelope.

## Phase 3 — Cross-path comparison

- Identify inconsistent context or prompt behavior across initial, regeneration, scheduled, and bulk generation.
- Check observability and correlation across asynchronous boundaries.
- Route scheduling/locking concerns to `.agents/agents/background-job-reviewer.md` rather than duplicating them.
- Record uncovered paths and verification limits.

## Phase 4 — Report

Use the shared severity/confidence model. Produce a pipeline map, cross-entry-point comparison, adjudicated findings, observability/test gaps, and prioritized remediation. Use the PR Xray report assembler only when artifacts are requested.

## References

- `AGENTS.md`
- `.agents/agents/generation-pipeline-reviewer.md`
- `.agents/agents/background-job-reviewer.md`
- `.agents/references/pr-xray-finding-contract.md`
