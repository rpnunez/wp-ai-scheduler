# 02 — Agentic Development Workflow

**Scope:** Part 2 of the audit (Requirements/Planning, Agent Delegation, Implementation, Multi-Turn Review, Testing & Verification).
**Method:** Local `git log` analysis on the current checkout, GitHub Actions workflow listing, reading of `AGENTS.md`/`CLAUDE.md` and the `.claude/` agent-and-skill directory tree, and `pull_request_read` on a sample of PRs. Read-only; no issues/PRs/comments were created or modified.
**Measured:** 2026-11-14.

---

## A. Requirements and planning — documented evidence

The repository contains a **dated design document preceding implementation** for its own agent-orchestration tooling:

- `.claude/docs/2026-11-11-pr-xray-design.md` and `.claude/docs/2026-11-11-pr-xray-implementation-plan.md` — a design doc and a separate implementation plan, both dated 2026-11-11, one day before the feature shipped in PR #2362 (merged 2026-11-12). This is a directly citable example of **plan-then-build**: the design doc defines a verdict taxonomy (Ready / Ready with non-blocking follow-ups / Needs changes / Blocked pending verification / Not ready) and reviewer routing rules; the implementation plan breaks that design into buildable steps; the PR then implements it.
- `AGENTS.md` itself functions as a living requirements/constraints document: architecture rules, coding conventions, the exact testing protocol agents must follow (`make test-session`), and an explicit "end-to-end feature delivery" section that invokes a `$feature-to-pr` skill for taking a feature from branch to PR. This is a standing planning artifact that every agent session (human-invoked or automated) reads before acting — the project treats "how we plan and deliver" as versioned, enforceable documentation rather than tacit knowledge.
- `.claude/skills/feature-slicing/` plus the `feature-slicer` subagent exist specifically to **break a feature request into independently testable, mergeable slices** along the plugin's own DB → service → AJAX → UI → tests dependency chain. This is planning-as-tooling: instead of writing one plan document per feature, the planning *process itself* is encoded as a reusable skill.

**Distinguish:** this is strong evidence that planning-before-implementation is an established practice *for this repository's own tooling*. It is weaker evidence (not independently verified per-feature) that every application feature PR was preceded by an equivalent written plan — some clearly were (the PR-Xray case above, the multi-PR slice series in §C), others show no such artifact in the PR body.

## B. Agent delegation and parallel work — what the evidence actually supports

**Confirmed, tool-identifying evidence** of multiple distinct AI coding agents having written commits in this repository (`git log --format='%an|%ae' | sort | uniq -c` against the current branch's history, 432 total commits):

| Identity | Commits | Role |
|---|---|---|
| Raymond Nunez `rpnunez@gmail.com` | 356 | Primary human author |
| Raymond `raypn93@gmail.com` | 24 | Same person, second email |
| **Claude** `noreply@anthropic.com` | 19 | AI coding agent (Anthropic) |
| **Cursor Agent** `cursoragent@cursor.com` | 17 | AI coding agent (Cursor background agent) |
| `rpnunez` (GitHub noreply) | 11 | Web-UI / Jules-initiated merges attributed to the GitHub account |
| **google-labs-jules[bot]** | 4 | AI coding agent (Google Labs "Jules") |
| `insanityzr1@gmail.com` | 1 | — |

Separately, **GitHub's own Actions workflow list** (independent of commit authorship) shows four active "coding agent" workflow entries wired into this repository: `dynamic/agents/anthropic-code-agent` ("Claude"), `dynamic/agents/openai-code-agent` ("OpenAI Codex"), `dynamic/copilot-swe-agent/copilot` ("Copilot cloud agent"), and `dynamic/copilot-pull-request-reviewer/copilot-pull-request-reviewer` ("Copilot code review"). This is independent, platform-level confirmation that **three distinct AI coding-agent products — Claude, OpenAI Codex, and GitHub Copilot — were formally connected as GitHub coding agents**, on top of the two further agents (Cursor Agent, Google Jules) visible only through commit authorship.

**What this does and does not prove:**

- It proves at least five distinct AI tooling integrations touched this codebase over its history: Claude (CLI/agent, with explicit `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>` trailers on some commits), Cursor's background agent, Google Labs Jules, and GitHub-native Copilot and Codex agent workflows.
- It does **not** by itself prove concurrent, orchestrated multi-agent collaboration on a single task (e.g., two agents working the same feature simultaneously with explicit handoffs). The clearest evidence of *deliberate* delegation is the PR-Xray system itself (§C below), which explicitly assigns different review *roles* — AJAX/security, DB/migration, background-job, l10n, generation-pipeline, bootstrap/dependency, QA — to named subagents and a coordinator/adjudicator pair, run against one PR at a time. That is role-based delegation within a review pipeline, which is a real and citable form of "different agents for different tasks," distinct from (and more specific than) a claim of general multi-agent parallelism.
- Jules-authored commits carry whimsical codename prefixes (`Atlas:`, `Bolt:`, `Palette:`) that appear to be per-task session names rather than feature names — e.g. `Atlas: Extract JSON parsing logic into AIPS_JSON_Extractor (#1909)`, `⚡ Bolt: Prevent N+1 queries in Generated Posts controller (#1919)`, `🎨 Palette: Add accessible labels to author table checkboxes (#1953)`. This is consistent with running several independent Jules sessions against different, unrelated maintenance tasks — a legitimate form of task-parallel agent delegation, though each task here is small/independent (a performance fix, an accessibility fix, a UI-flow fix) rather than a single large feature split across agents.

## C. Implementation — the PR-Xray pipeline as the clearest delegation artifact

The `.claude/` directory implements a genuine custom multi-agent code-review system, not just prompt templates:

- **12 named domain/role agents** in `.claude/agents/`: `ajax-controller-reviewer`, `db-migration-reviewer`, `background-job-reviewer`, `l10n-reviewer`, `generation-pipeline-reviewer`, `bootstrap-dependency-reviewer`, `feature-slicer`, `pr-portfolio-analyst`, `pr-xray-coordinator`, `pr-xray-adjudicator`, `pr-xray-report-assembler`, `qa-reviewer`.
- **Shared contracts**, not ad hoc prompting: `.claude/references/pr-xray-finding-contract.md` (what a finding must contain), `pr-xray-severity-model.md` (how severity is assigned), `pr-xray-reviewer-routing.md` (which reviewer owns which kind of change).
- **Workflows** that compose these agents for specific jobs: `.claude/workflows/pr-xray.md` (deep single-PR review), `ajax-security-sweep.md` (fan-out one agent per AJAX controller), `db-migration-audit.md`, `generation-pipeline-sweep.md`, `pr-portfolio-triage.md` (rank multiple open PRs without claiming deep-review approval — a deliberate scope limitation written into the tooling itself).
- A **coordinator/adjudicator split**: `pr-xray-coordinator` resolves a PR's intent, commits, and reviewer routing; domain reviewers run read-only; `pr-xray-adjudicator` consolidates findings, resolves duplicates/contradictions, and assigns the final verdict; `pr-xray-report-assembler` writes the packaged report and a downloadable ZIP. This is a real orchestrator → specialist(s) → adjudicator → publisher pipeline shape — the same shape used for multi-agent code review systems generally, implemented here specifically for a WordPress plugin's own domain boundaries.

**This pipeline was itself built via an agent-assisted PR**: PR #2362, "Improve PR triage with PR Portfolio Triage and PR Xray" (merged 2026-11-12, +1105/-454 across 35 files). Its description documents "Approved Plan Alignment," explicit **read-only vs. write/execute permission boundaries per agent** (e.g., `pr-xray-report-assembler` is the only agent with filesystem write/execute access; every reviewer agent is read-only), and a verification checklist. This is the single best case study in the repository for "used an AI agent to design and build an agent-orchestration system with explicit least-privilege boundaries" — expanded further in `03-pr-review-case-studies.md`.

## D. Multi-turn review and feedback — concrete, citable examples

The audit's instruction is explicit: **do not describe a review as multi-turn unless multiple rounds are actually documented.** The following examples meet that bar; others that do not are flagged as such.

1. **PR #2177 → PR #2178** (Jules "Atlas" persona). #2177, "Atlas: Refactor `build_history_timeline_data` God Method into cohesive helper functions," was closed without a recorded merge. #2178 is explicitly titled "...harden fallback logic for history timeline builder (#2177 review)," with a body reading: *"Address findings from Code Review on PR #2177: 1. Added unit tests... 2. Hardened fallback logic... 3. Updated DocBlock parameter types..."* This is a genuine initial-implementation → review-findings → revised-implementation sequence. **Caveat:** neither PR shows `merged: true` in the GitHub API response — the final shipped version of this refactor cannot be confirmed to have landed under these exact PR numbers; do not claim this exact pair merged.

2. **PR series #2207–#2214** (one logical task split into a mergeable chain, Claude co-authored). #2207 "Fix author post generation running twice on same topic (#2203)" led into a dedup-and-dedupe-test chain: #2208 ("list each draft once in Pending Review," referenced as "PR 1" of the series) → #2209 ("PR 2") → #2210 ("PR 3") → #2212 → #2214 (adds a `record_kind` column). Commit `2b80397` on #2208, fixing duplicate Pending Review rows, carries `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`. A **same-PR self-correction** follows in commit `7234379` — "test(review): compare post IDs as integers..." — which fixes a test bug the first commit had introduced. This is a real, small-scale review → fix → test-fix cycle within one PR, and a real example of a complex change being deliberately sliced into a sequence of independently mergeable PRs (the pattern the `feature-slicer` skill formalizes).

3. **PR #2320**, "Topic Silos & Hub-and-Spoke Linking for Campaigns" (Cursor Agent-authored, merged, +925/-23 across 19 files). The PR body contains **two stacked, dated revision blocks** (`<!-- CURSOR_AGENT_PR_BODY -->`), where the second revision adds a safety fix not present in the first: a new `claim_pillar_post_if_unset()` method and a new test file, `Test_AIPS_Campaign_Silo_Safety.php`, specifically guarding against a race condition where concurrent generation could double-claim a pillar post. This reads as the agent (or a reviewer prompting the agent) surfacing a concurrency edge case after the initial implementation and shipping a second, tested revision in the same PR — legitimate evidence of iterative hardening, though it is a single-PR revision cycle rather than a GitHub-native multi-round review thread.

4. **PR #2184**, "Atlas: Refactored AIPS_Generator God Method" — `get_reviews` returned **zero** review objects for this PR. **This is explicitly NOT a multi-turn-reviewed example** and should not be cited as one; it is listed here only to document the negative case the audit asked to distinguish.

5. **PR #2342**, "perf(prompts): eliminate input token bloat across generation pipeline" (Jules-authored, merged) quantifies three distinct token-savings fixes (~300, ~125-375, ~1,500-2,500 tokens/post) with named root causes and accompanying unit tests. This is a single well-documented implementation rather than a multi-round review case, but it's strong evidence for Part 3 (production AI engineering — cost/efficiency-aware prompt design) and is discussed further as a case study in `03-pr-review-case-studies.md`.

## E. Testing and verification

- **Tests that exist vs. tests demonstrably run in-workflow:** CI (`ci-pr.yml`, `phpunit-tests.yml`) runs the full 234-file PHPUnit suite on every PR and push to `main`/`develop`, with a `test-summary` gate job in `phpunit-tests.yml` that fails the run if the test job itself did not succeed — this is a demonstrable, automated "tests must pass" gate, not merely tests that exist unused in the tree.
- **Agent-specific test protocol:** `AGENTS.md`/`TESTING.md`'s "Agent test protocol" mandates `make test-session ARGS="tests/Test_A.php tests/Test_B.php"` for agent-run tests specifically — a containerized run against a separate `wp_tests` database that stops the containers it started. This is a designed safeguard against an agent leaving infrastructure running or testing against the wrong database, and it is referenced directly by `CLAUDE.md`'s "Testing policy" section quoted at the top of this audit's own instructions.
- **Static analysis / boundary enforcement:** `composer lint:repository-boundary`, run in CI, is a project-specific static check (not a generic linter) — a concrete example of encoding an architectural rule as an automated gate that would catch an AI agent's (or a human's) layer-violating change before merge.
- **Security-oriented review role:** the `ajax-controller-reviewer` subagent is scoped specifically to authorization, nonce handling, typed input, response shape, and denied-path coverage for AJAX controllers — a standing, reusable security-review role rather than a one-off audit.
- **Filename-level evidence of review-driven fixes:** `Test_AIPS_Background_Process_Review_Fixes.php` is a test file whose name documents that it exists specifically to cover fixes that came out of a review pass on the background-process subsystem — i.e., review findings were operationalized as permanent regression tests, not just one-time patches.

## Unverifiable / explicitly flagged

- Private agent session transcripts (Jules, Cursor, Codex, Claude Code sessions not captured in PR/commit text) cannot be inspected from this repository alone — any claim about *what reasoning happened inside* those sessions beyond what a PR body documents is inference, not verified fact.
- The true count of Jules-originated PRs is almost certainly higher than the 4 bot-authored commits suggest, since Jules PRs are frequently merged/squashed under the human account's identity (`rpnunez`, 11 commits via GitHub noreply) — exact attribution per PR would require per-PR inspection beyond this pass's budget.
- A full, exhaustively paginated count of merged PRs was not completed in this pass (the sample covered PRs #2176–#2374, ~100 most recent); see `05-quantitative-evidence.md` for the exact methodology and what remains an estimate versus a hard count.
- Personal author emails are deliberately not published in any resume-facing document; they appear here only as repository-internal evidence.
