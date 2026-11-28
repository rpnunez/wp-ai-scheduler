# 03 — PR & Review Case Studies

**Scope:** Part 5 of the audit (5–8 significant historical case studies).
**Method:** `pull_request_read` / `list_pull_requests` against `rpnunez/wp-ai-scheduler`, cross-referenced with local `git log`/`git show`. Read-only — no PRs, issues, or comments were created, edited, or reacted to.
**Measured:** 2026-11-14.

---

## Case Study 1 — Building the agent-orchestration system itself (PR #2362)

1. **Problem:** PR review and portfolio triage for this repository had become something the maintainer needed repeatable, structured tooling for — not a code feature, but a process feature.
2. **PR:** [#2362 — "Improve PR triage with PR Portfolio Triage and PR Xray"](https://github.com/rpnunez/wp-ai-scheduler/pull/2362), merged 2026-11-12. +1105/-454 across 35 files.
3. **Agent involvement:** Directly evidenced — the PR body documents "Approved Plan Alignment" against a prior design, and the shipped artifact *is* an agent system (12 named subagents, shared contracts, workflows).
4. **Planning/architectural decisions:** Preceded by two dated design artifacts, `.claude/docs/2026-11-11-pr-xray-design.md` and `.claude/docs/2026-11-11-pr-xray-implementation-plan.md` (both dated the day before merge) — defining a verdict taxonomy (Ready / Ready with non-blocking follow-ups / Needs changes / Blocked pending verification / Not ready) and reviewer-routing rules before any agent code was written.
5. **Implementation approach:** A coordinator → specialist(s) → adjudicator → report-assembler pipeline. Each specialist (`ajax-controller-reviewer`, `db-migration-reviewer`, `background-job-reviewer`, `l10n-reviewer`, `generation-pipeline-reviewer`, `bootstrap-dependency-reviewer`, `qa-reviewer`) is scoped to one domain and granted **read-only** tool access; `pr-xray-coordinator` resolves intent/commits/routing; `pr-xray-adjudicator` consolidates and dispositions findings; `pr-xray-report-assembler` is the only agent with write/execute access, producing the final packaged report and ZIP.
6. **Review/feedback iterations:** The PR body itself documents a verification checklist against the approved plan — this is evidence of self-review against a written spec rather than a GitHub-thread review cycle.
7. **Final outcome:** Merged, and the resulting system is listed as live tooling in this repo's own `CLAUDE.md` ("Project-scoped subagents... auto-delegated... or invoked by name").
8. **Transferable skills demonstrated:** Multi-agent system design with explicit role boundaries and least-privilege tool access; design-doc-first engineering process; building internal developer tooling (a code-review/triage platform) rather than only product features — directly relevant to platform/tooling and "AI-assisted engineering process" narratives.
9. **Evidence:** PR #2362 body/diff; `.claude/agents/*.md`; `.claude/references/pr-xray-finding-contract.md`, `pr-xray-severity-model.md`, `pr-xray-reviewer-routing.md`; `.claude/workflows/pr-xray.md`; `.claude/docs/2026-11-11-pr-xray-design.md`, `...-implementation-plan.md`.

---

## Case Study 2 — Multi-turn review on a refactor: history-timeline "God Method" (PR #2177 → #2178)

1. **Problem:** `build_history_timeline_data` had grown into a "God Method" needing decomposition into cohesive helper functions, with fallback-logic gaps.
2. **PRs:** [#2177](https://github.com/rpnunez/wp-ai-scheduler/pull/2177) — "Atlas: Refactor `build_history_timeline_data` God Method"; [#2178](https://github.com/rpnunez/wp-ai-scheduler/pull/2178) — follow-up explicitly titled "...harden fallback logic for history timeline builder (#2177 review)."
3. **Agent involvement:** Jules (Google Labs), operating under the session codename "Atlas," per commit-message convention.
4. **Planning:** Not independently documented beyond the PR title/scope; this case is about review iteration, not up-front planning.
5. **Implementation approach:** #2177 extracted a monolithic method into smaller, named helpers (4 commits, +164/-23, 5 files).
6. **Review/feedback iterations:** #2178's body enumerates the review findings addressed verbatim: "1. Added unit tests... 2. Hardened fallback logic... 3. Updated DocBlock parameter types..." — a documented review → fix mapping, which is the clearest "comment identified issue → commit addressed it" artifact found in this audit.
7. **Final outcome:** **Flagged, not fully confirmed** — neither #2177 nor #2178 shows `merged: true` in the GitHub API response at measurement time. The refactor's underlying intent likely landed through other means (possibly re-authored or superseded), but this exact PR pair cannot be cited as "merged." Use this case only to illustrate the *review-iteration pattern*, not as proof the specific diff shipped.
8. **Transferable skills demonstrated:** Responding to concrete review feedback with targeted, itemized fixes rather than a wholesale rewrite; treating "add tests" as a first-class review finding, not an afterthought.
9. **Evidence:** PR #2177 and #2178 bodies/commit lists via `pull_request_read`.

---

## Case Study 3 — Incremental delivery of a correctness fix as a mergeable PR chain (PRs #2207–#2214)

1. **Problem:** Author post generation was running twice for the same topic, producing duplicate rows in the Pending Review list.
2. **PRs:** #2207 ("Fix author post generation running twice on same topic (#2203)") through a chain labeled "PR 1/2/3" in sequence — #2208, #2209, #2210 — followed by #2212 and #2214 (adds a `record_kind` column to disambiguate record types going forward).
3. **Agent involvement:** Claude — commit `2b80397` on #2208 carries `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
4. **Planning:** The root cause (#2203) was isolated first, then the fix was deliberately sliced into a sequence of independently mergeable PRs rather than one large change — the same DB → service → AJAX/UI → tests ordering the repo's `feature-slicer` skill formalizes.
5. **Implementation approach:** #2208 fixed the duplicate-row symptom directly in the Pending Review list query/rendering.
6. **Review/feedback iterations:** A same-PR self-correction: commit `7234379` ("test(review): compare post IDs as integers...") fixes a test-comparison bug that the first commit's own test had introduced (likely a loose `==` vs. strict-type comparison) — a small but real example of a test being corrected in response to its own review, within the same PR.
7. **Final outcome:** The chain continues through #2214, which adds a `record_kind` column — i.e., the short-term symptom fix was followed by a schema-level change to prevent the same class of bug structurally, rather than stopping at the patch.
8. **Transferable skills demonstrated:** Root-cause isolation before patching; slicing a fix into reviewable, independently deployable increments; following a symptom fix with a structural (schema) fix rather than treating the patch as "done."
9. **Evidence:** PR #2207–#2214 bodies and commit lists; commits `2b80397`, `7234379` via local `git show`.

---

## Case Study 4 — Agent self-hardening against a concurrency bug (PR #2320)

1. **Problem:** Implement "Topic Silos & Hub-and-Spoke Linking for Campaigns" — a content-architecture feature where a campaign's generated posts are organized around a pillar post with spoke posts linking back to it.
2. **PR:** [#2320](https://github.com/rpnunez/wp-ai-scheduler/pull/2320), merged. +925/-23 across 19 files.
3. **Agent involvement:** Cursor Agent (`cursoragent@cursor.com`), evidenced by two stacked `<!-- CURSOR_AGENT_PR_BODY -->` revision blocks in the PR description.
4. **Planning:** The second revision block documents a scope addition made *after* the first implementation pass: the agent (or a reviewer directing it) identified a race condition where concurrent post generation could let two posts both claim "pillar" status for the same campaign.
5. **Implementation approach:** First revision implemented the silo/hub-and-spoke linking logic; second revision added `claim_pillar_post_if_unset()` — an atomic claim operation preventing the double-claim race — plus a new test file, `Test_AIPS_Campaign_Silo_Safety.php`, specifically exercising concurrent-generation pillar-claim behavior.
6. **Review/feedback iterations:** Single PR, two documented revisions — the agent's own description shows the implementation being hardened in place rather than left for a separate follow-up PR.
7. **Final outcome:** Merged with the concurrency-safety fix and its regression test included, not deferred.
8. **Transferable skills demonstrated:** Recognizing and closing a concurrency/race-condition gap in a feature before merge; writing a dedicated safety-focused test file rather than folding the check into an unrelated test; treating "what happens under concurrent requests" as a first-class design question for a content-generation feature — directly relevant to backend engineering in any stack with concurrent request handling.
9. **Evidence:** PR #2320 body (both revision blocks), diff, `Test_AIPS_Campaign_Silo_Safety.php`.

---

## Case Study 5 — AI-functionality cost/efficiency engineering (PR #2342)

1. **Problem:** The generation pipeline's prompts were carrying avoidable token bloat across multiple call sites, directly increasing per-post LLM cost and latency.
2. **PR:** [#2342 — "perf(prompts): eliminate input token bloat across generation pipeline"](https://github.com/rpnunez/wp-ai-scheduler/pull/2342), merged.
3. **Agent involvement:** Jules (Google Labs), per repository convention for Jules-authored PRs.
4. **Planning:** The PR body itemizes three distinct, independently named root causes of token waste (quantified at roughly 300, 125–375, and 1,500–2,500 tokens per post respectively) rather than a single generic "optimize prompts" claim — evidence of root-cause-level analysis before the fix.
5. **Implementation approach:** Each of the three fixes targets a specific overbuilt prompt-assembly path in the generation pipeline.
6. **Review/feedback iterations:** Not documented as multi-round in the PR body; the strength of this case study is the quantified, per-cause savings analysis, not an iteration cycle.
7. **Final outcome:** Merged, with accompanying unit tests for the changed prompt-assembly behavior.
8. **Transferable skills demonstrated:** Treating LLM input tokens as a first-class cost/performance metric to profile and reduce, with before/after quantification per root cause — the same discipline as optimizing payload size or query cost in any API-cost-sensitive system.
9. **Evidence:** PR #2342 body (quantified savings breakdown), diff.

---

## Case Study 6 — Enforcing architecture as CI, not documentation (repository-boundary lint)

1. **Problem:** The controller/repository/service/template layering described in `CLAUDE.md` is only useful if violations are caught automatically, especially when AI agents are generating a large share of the code.
2. **Artifact:** `composer lint:repository-boundary` (Composer script) backed by `tools/check-repository-boundary.php`, run as a required step in `.github/workflows/ci-pr.yml` ("Enforce repository boundary (no direct wpdb in controllers/services)").
3. **Agent involvement:** Not independently attributed to a specific agent in this pass; included here because it is the architectural safeguard that makes every other agent-authored PR in this audit trustworthy — any layering violation from any contributor (human or AI) is caught before merge, not after.
4. **Planning/decision:** Converting a documented convention into an automated, CI-gated check is itself an architectural decision — choosing to spend engineering effort on a custom static-analysis tool rather than relying on review discipline alone.
5. **Implementation approach:** A dedicated PHP script walks the `includes/` tree and fails the build if `$wpdb` usage appears outside a repository class.
6. **Review/feedback:** N/A — this is infra, not a single reviewed feature.
7. **Final outcome:** Live, required CI gate as of the measurement date (subject to the caveat in `01-architecture-and-ai-capabilities.md` that several *other* CI workflows show `disabled_manually` — this specific lint step was confirmed present in `ci-pr.yml`, not independently re-confirmed as currently enabled vs. disabled in the live Actions list).
8. **Transferable skills demonstrated:** Translating an architectural rule into a custom static-analysis gate; designing guardrails specifically to keep AI-agent-authored and human-authored code to the same standard.
9. **Evidence:** `ai-post-scheduler/composer.json` (`lint:repository-boundary` script); `.github/workflows/ci-pr.yml`.

---

## Case Study 7 — Role-scoped security review as a standing capability (`ajax-controller-reviewer`)

1. **Problem:** Every AJAX controller is a potential authorization/CSRF/input-validation surface, and the plugin has 39 controller classes routed through one central registry — a natural place for a recurring, consistent security review.
2. **Artifact:** The `ajax-controller-reviewer` subagent (`.claude/agents/ajax-controller-reviewer.md`), scoped specifically to registry routing, authorization, nonce handling, typed input parsing, response-shape compatibility, and denied-path coverage; composed with the `.claude/workflows/ajax-security-sweep.md` workflow, which fans out one reviewer instance per AJAX controller.
3. **Agent involvement:** Direct — this *is* an agent definition, not a feature it reviewed.
4. **Planning:** Scoping a reviewer to a single architectural seam (the AJAX boundary) rather than a generic "review this code" prompt reflects the same routing/contract discipline seen in the PR-Xray system (Case Study 1).
5. **Implementation approach:** Read-only tool access only — the reviewer cannot modify code, only report findings, consistent with the least-privilege design across all PR-Xray-era subagents.
6. **Review/feedback:** N/A — this is the review *capability* itself.
7. **Final outcome:** A standing, reusable security-review role, invokable on demand or via the sweep workflow, rather than a one-time audit.
8. **Transferable skills demonstrated:** Designing a recurring, narrowly-scoped security review process for a specific trust boundary (the equivalent, in a Node/Express app, of a standing reviewer scoped to every `app.post('/api/...')` handler for authn/authz and input validation) — this maps almost one-to-one onto API-security review practice in any REST/AJAX backend.
9. **Evidence:** `.claude/agents/ajax-controller-reviewer.md`; `.claude/workflows/ajax-security-sweep.md`; `CLAUDE.md` subagent table.

---

## Notes on what was deliberately excluded

- A full merged-PR count and a complete enumeration of every Jules/Cursor/Codex-authored PR were not completed (see `05-quantitative-evidence.md` for the exact sampling methodology and its limits). The case studies above were chosen because each has a directly readable PR body and diff, not because they are necessarily the single largest PRs by line count.
- PR #2184 ("Atlas: Refactored AIPS_Generator God Method") was considered and excluded as a case study because `get_reviews` returned zero review objects — it does not meet the "multiple rounds documented" bar and should not be cited as a reviewed example.
