# 05 — Quantitative Evidence

**Scope:** Part 6 of the audit.
**Method and date:** All counts below were produced on 2026-11-14 via local `git`/`find`/`wc` commands against the checked-out branch (`ccr-b711ef3a-s6b7nf`, descended from `main`) and via GitHub API calls (`mcp__github__*`) against `rpnunez/wp-ai-scheduler`. Where a count is a sample rather than an exhaustive total, that is stated explicitly — this audit does not round up partial samples into totals.

---

## Methodology notes (read before citing any number below)

- **Commit counts** reflect the *current local branch's* history only (432 commits reachable from HEAD at measurement time), via `git log --format='%an|%ae' | sort | uniq -c`. This is not necessarily identical to "all commits ever merged to `main`," since branch history can differ from the default branch's exact commit graph after squash-merges.
- **PR counts** reflect a *sample* of the 100 most recently created PRs (`list_pull_requests`, sorted by creation date descending), spanning PR numbers #2176–#2374 (created Oct 13 – Nov 13, 2026). This is **not a full paginated count** of every PR in the repository's history (the repo clearly has PRs well below #2176, given issue/PR numbering). Any total below labeled "sampled" should not be read as "total ever."
- **Test/LOC counts** are exact `find`/`wc -l` results against the current checkout, excluding `vendor/`.

---

## Repository scale

| Metric | Count | Source |
|---|---|---|
| PHP files under `includes/` | 324 | `find` |
| PHP LOC (`ai-post-scheduler/**/*.php`, excl. vendor) | ~234,834 | `find \| xargs wc -l` |
| JS files under `assets/js/` | 53–64 (count varies slightly by glob depth used across the two research passes) | `find` |
| JS LOC under `assets/js/` | ~40,569 | `find \| xargs wc -l` |
| PHPUnit test files (`tests/Test_*.php`) | 234 | `find` |
| JS test files | 1 (`tests/js/pending-review-selection.test.js`, 499 lines) | `find`/`grep` |
| GitHub Actions workflow files | 21 | `actions_list` |
| Named `.claude/` subagents | 12 | `find .claude/agents` |
| Named `.claude/` skills | 10 | `AGENTS.md`/`CLAUDE.md` skill table |
| Named `.claude/` saved workflows | 5 | `CLAUDE.md` workflows table |

## Commit authorship (current branch history, 432 commits total)

| Identity | Commits | % of total |
|---|---|---|
| Raymond Nunez (`rpnunez@gmail.com`) | 356 | 82.4% |
| Raymond (`raypn93@gmail.com`, same person) | 24 | 5.6% |
| **Claude** (`noreply@anthropic.com`) | 19 | 4.4% |
| **Cursor Agent** (`cursoragent@cursor.com`) | 17 | 3.9% |
| `rpnunez` via GitHub noreply (web UI / Jules-initiated merges under the human account) | 11 | 2.5% |
| **google-labs-jules[bot]** | 4 | 0.9% |
| Other (1 address) | 1 | 0.2% |

**Reading these numbers correctly:** 88% of commits (356+24+11) are attributed to the project owner's own identities, including commits merged through the GitHub web UI that may have originated from Jules sessions. **~9.2%** of commits (19+17+4 = 40 commits) carry a *tool-identifying* AI-agent author/email directly in the commit metadata. This 9.2% is a floor, not a ceiling — it only counts commits where the agent's own identity was the committer; it does not count human-authored commits that were drafted with AI assistance but committed under the human identity (which cannot be distinguished from git metadata alone).

## GitHub Actions — coding-agent integrations

Four GitHub-native coding-agent workflow entries, confirmed `state: active` at measurement time, independent of the commit-authorship evidence above:

1. `dynamic/agents/anthropic-code-agent` ("Claude")
2. `dynamic/agents/openai-code-agent` ("OpenAI Codex")
3. `dynamic/copilot-swe-agent/copilot` ("Copilot cloud agent")
4. `dynamic/copilot-pull-request-reviewer/copilot-pull-request-reviewer` ("Copilot code review")

This gives **5 distinct AI tooling integrations with direct repository evidence**: Claude, Cursor Agent, Google Labs Jules (via commit authorship), plus GitHub-native Codex and Copilot agent workflows (via Actions registration) — noting that GitHub-native Codex/Copilot workflow registration does not by itself prove they authored commits found in the sample; it proves they were connected and available to the repository.

## Pull requests (sampled, #2176–#2374, ~100 most recent at measurement time)

| Status | Approx. count in sample |
|---|---|
| Merged | ~78 |
| Closed without merge / draft | ~22 |

**Flagged limitation:** this is a ~100-PR sample of the most recent activity, not a full historical count. A defensible resume claim should say "dozens of merged PRs reviewed in a sampled window," not state an unqualified total PR count — the audit did not complete a full paginated count of every PR since repository creation.

## Issues

- **277 total issues** (open + closed combined), via `list_issues` total count.

## Review/revision cycles documented (meeting the "multiple rounds actually documented" bar)

| # | Case | Evidence |
|---|---|---|
| 1 | PR #2177 → #2178 | Follow-up PR body itemizes specific review findings addressed (tests, fallback logic, docblocks) |
| 2 | PR #2207–#2214 chain | Same-PR self-correction commit (`7234379`) fixing a test bug from an earlier commit (`2b80397`) in the same chain |
| 3 | PR #2320 | Two stacked Cursor-agent PR-body revisions, second adding a concurrency-safety fix and dedicated test |
| 4 | PR #2362 (PR-Xray system) | PR body documents a verification checklist against a prior approved design doc |

**Count: 4 documented review/revision cycles** meeting the audit's bar for "multi-turn," out of the PRs actually opened and read in this pass (a larger number likely exists in the full, un-sampled PR history but was not verified).

## Significant architectural refactors identified

1. Repository-boundary CI lint (enforcing controller/service/repository/template separation as code, not just convention).
2. The context-object generation pipeline (`AIPS_Generation_Context`/`AIPS_Template_Context`/`AIPS_Topic_Context` + `AIPS_Generation_Context_Factory`), replacing ad hoc parameter passing.
3. The managed background-process framework (`AIPS_Managed_Background_Process` + quota/cooldown-aware subclasses), unifying what were presumably separate ad hoc cron jobs into one lifecycle.
4. The AI provider abstraction + null-object fallback (`AIPS_AI_Provider_Factory`), decoupling generation logic from any single AI backend.
5. The PR-Xray multi-agent review pipeline itself (PR #2362) — a refactor of the *development process*, not application code.

**Count: 5** significant architectural refactors with direct code or PR evidence (see `01-architecture-and-ai-capabilities.md` and `03-pr-review-case-studies.md` for citations). This is a conservative count — it excludes smaller refactors (e.g., the "God Method" decompositions in #2177, #2184) that are real but narrower in scope.

## Distinct AI-related subsystems in the plugin itself (Part 3)

1. AI provider abstraction (`AIPS_AI_Provider_Factory` + provider classes)
2. AI service/resilience layer (`AIPS_AI_Service`, `AIPS_Resilience_Service`)
3. AI error classification (`AIPS_AI_Error_Classifier`)
4. Embeddings/semantic similarity (`AIPS_Embeddings_Service` + 5 related classes)
5. Generation context/regeneration pipeline (`AIPS_Generation_Context_Factory` + context classes)
6. Managed background execution for AI-backed jobs (`AIPS_Managed_Background_Process`, `AIPS_Embeddings_Background_Process`)
7. Observability (`AIPS_Generation_Logger`, `AIPS_History_Service`, `AIPS_Correlation_Id`)

**Count: 7** distinct, independently named AI-related subsystems with direct class-level evidence.

## What this audit explicitly does NOT claim

- An exact total merged-PR count for the repository's full history (sample only — see limitation above).
- A precise count of PRs authored specifically by Jules vs. merged under the human identity (git metadata cannot distinguish these cleanly).
- Any production usage metrics (number of posts generated, API spend, user counts) — none were requested to be measured and none were found exposed in this repository; do not invent these for a resume.
- Any performance/cost improvement percentages beyond the specific, PR-documented token-count figures in PR #2342 (~300, ~125–375, ~1,500–2,500 tokens/post for three named root causes) — these are the only quantified AI-cost figures found with direct citation; do not generalize them into an unqualified "reduced AI costs by X%" claim.
