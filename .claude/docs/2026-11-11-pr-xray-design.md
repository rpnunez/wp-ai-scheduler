# PR Portfolio Triage and PR Xray Design

## Intent

Separate two previously conflated jobs:

- **PR Portfolio Triage** ranks and routes multiple open pull requests.
- **PR Xray** performs an evidence-based, multi-reviewer examination of one pull request or branch.

All canonical capability definitions for this phase live under `.claude/`. `AGENTS.md` remains the authority for project-wide engineering and testing rules.

## Goals

- Remove duplicated portfolio and deep-review responsibilities from the old `pr-triage` capability.
- Give each domain reviewer a narrow, evidence-based responsibility.
- Normalize findings through one shared contract and severity model.
- Validate, deduplicate, and adjudicate reviewer findings before assigning a verdict.
- Generate individual Markdown reports, machine-readable JSON, a manifest, and a validated ZIP archive.
- Keep domain reviewers read-only; centralize artifact writes in a report assembler.

## Non-goals

- Changing WordPress runtime code, plugin settings, schema, AJAX, cron, or generation behavior.
- Synchronizing the new canonical design into `.codex` or `.github` during this phase.
- Treating missing runtime verification as either a pass or a defect.
- Replacing the codebase-wide AJAX, database, or generation sweeps.

## Architecture

### PR Portfolio Triage

Portfolio Triage collects current PR metadata, identifies overlap and blockers, ranks work, and recommends review/merge order. It may recommend PR Xray for an individual high-risk change but does not perform a deep code review.

### PR Xray

PR Xray processes one change set through these stages:

1. Resolve PR intent, acceptance criteria, base, and head.
2. Inventory changed files and select applicable reviewers.
3. Run domain reviewers and focused verification.
4. Adjudicate evidence, duplicates, contradictions, and coverage gaps.
5. Assemble human- and machine-readable reports and a validated ZIP.

## Responsibility boundaries

- **Skills** define triggers, scope, workflow selection, and output expectations.
- **Agents** make one specialized class of judgment and return structured evidence.
- **Workflows** coordinate order, routing, verification, adjudication, and artifacts.
- **References** define shared contracts once so reviewers do not duplicate them.
- **`AGENTS.md`** owns project-wide WordPress, architecture, security, and testing policy.

## Reviewers

PR Xray can route to AJAX, database/migration, generation pipeline, background-job, localization, bootstrap/dependency, and QA reviewers. QA always participates. Other reviewers are selected by the rules in `../references/pr-xray-reviewer-routing.md`.

## Findings and verdicts

Every reviewer follows `../references/pr-xray-finding-contract.md` and `../references/pr-xray-severity-model.md`. The adjudicator may accept, merge, adjust, defer for verification, or reject a submitted finding.

Final verdicts are:

- Ready
- Ready with non-blocking follow-ups
- Needs changes
- Blocked pending verification
- Not ready

## Artifacts

The report assembler is the only PR Xray agent that writes artifacts. It writes under `.artifacts/pr-xray/<safe-run-id>/`, validates the manifest and allowlisted files, creates the ZIP, inspects the archive, and reports absolute paths. Domain agents remain read-only.

## Constraints

- No unsupported claim may be labeled confirmed.
- Every changed file must be assigned to a reviewer or explicitly excluded.
- Every acceptance criterion must receive a verification status.
- Reports must distinguish inspected, executed, inferred, unavailable, and failed verification.
- Archives must exclude secrets, environment files, unrelated logs, source trees, temporary files, and the archive itself.

## Acceptance criteria

- Portfolio Triage and PR Xray have distinct names, triggers, workflows, agents, and outputs.
- Existing reviewers use the shared contract and their updated domain boundaries.
- Background-job review covers all asynchronous mechanisms, not only bulk batches.
- Bootstrap/dependency review is available and conditionally routed.
- Adjudication validates evidence and coverage before issuing a verdict.
- Individual Markdown reports, `findings.json`, `manifest.json`, and a validated ZIP are specified.
- Active `.claude` references resolve and retired names are removed.
- `.codex`, `.github`, and plugin runtime files remain unchanged.
