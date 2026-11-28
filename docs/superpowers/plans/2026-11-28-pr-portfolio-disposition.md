# PR Portfolio Disposition Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a repeatable inventory and a merge/close disposition report for every open pull request, using labels, commit volume, comment volume, and review-thread state as the decision base.

**Architecture:** A Node script pages the GitHub GraphQL API (via `gh api graphql`), writes a raw inventory JSON, then a pure scorer assigns each PR a disposition. A reporter renders JSON plus Markdown. Nothing in this pipeline reviews code or approves a merge. The existing `.github/workflows/pr-triage-daily.yml` job stays as the daily top-10 complexity digest; this report is the full open-set disposition.

**Tech Stack:** Node 20 (no new npm dependency), `gh` CLI authenticated for the repo, `node --test`.

## Global Constraints

- Read-only against GitHub. The scripts must not merge, close, comment, label, or push.
- Keep-merge labels, any one of which means the PR is on the eventual-merge track: `chances-high`, `fix`, `bugfix`, `bug-fix`. The live label set uses `bug-fix`, not `bugfix`; treat both spellings as the same signal.
- A portfolio disposition is not merge approval. The report must say that in the header.
- Do not extend `pr-triage-daily.yml` to do this job. That workflow caps at the 10 most recently committed of the first 50 updated PRs and omits review threads.
- Baseline measured 2026-11-28 02:22 UTC on `rpnunez/wp-ai-scheduler`: 63 unique open PRs. Buckets under the rules below: keep-label 5, keep-label-and-effort 6, effort-without-keep-label 19, thin 7, middle 26. Eleven PRs carry a keep-merge label.

## Decision rules the scorer must implement

Apply in this order. First match wins.

1. **merge-track** — any keep-merge label. Conflicts, age, and open threads do not move it off this track. The report still lists conflict and unresolved-thread remediation, because those block the merge even though the PR is kept.
2. **salvage** — no keep-merge label, and effort is high: `commitCount >= 8`, or (`commitCount >= 6` and `issueComments + reviewCount >= 3`). This is the “more than 5–10 commits plus discussion” keep signal, including conflicting PRs.
3. **close-candidate** — no keep-merge label, `commitCount <= 3`, `issueComments == 0`, `reviewCount == 0`, `unresolvedThreads == 0`, and (`mergeable == CONFLICTING` or `updatedAt` older than 21 days).
4. **review-then-decide** — everything else. These need a human pass; the report must not recommend close.

Secondary fields, recorded on every row and used in the narrative, not as extra buckets:

- `reviewRounds`: count of review submissions (`reviews.totalCount`).
- `resolvedThreads` / `unresolvedThreads` from `reviewThreads.nodes[].isResolved`.
- `latestReviewState` from the newest review `submittedAt`.
- `hasApproval`: any review state `APPROVED`.
- `behind`: `mergeable == CONFLICTING` (GitHub’s conflicting flag; do not compute commit distance from `main` in v1).

## File structure

- Create: `scripts/pr-portfolio/inventory.mjs` — GraphQL paging, writes raw facts.
- Create: `scripts/pr-portfolio/score.mjs` — pure `scorePullRequest(raw)` and `scoreAll(rawList)`.
- Create: `scripts/pr-portfolio/report.mjs` — CLI: inventory path in, JSON + Markdown out.
- Create: `scripts/pr-portfolio/score.test.mjs` — node:test cases for the four dispositions.
- Modify: `Makefile` — add `pr-portfolio` target.
- Modify: `.claude/workflows/pr-portfolio-triage.md` — point Phase 1 at the generated inventory.

---

### Task 1: Scorer

**Files:**
- Create: `scripts/pr-portfolio/score.mjs`
- Test: `scripts/pr-portfolio/score.test.mjs`

**Interfaces:**
- Consumes: one inventory object (shape fixed in this task; Task 2 must emit it).
- Produces: `scorePullRequest(pr) -> { number, disposition, reasons: string[], signals }`. `disposition` is `merge-track` | `salvage` | `close-candidate` | `review-then-decide`.

- [ ] **Step 1: Write the failing test**

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { scorePullRequest } from './score.mjs';

const base = {
  number: 1,
  labels: [],
  commitCount: 1,
  issueComments: 0,
  reviewCount: 0,
  resolvedThreads: 0,
  unresolvedThreads: 0,
  mergeable: 'MERGEABLE',
  updatedAt: '2026-11-27T00:00:00Z',
  now: '2026-11-28T00:00:00Z',
};

test('keep-merge label wins over conflicts and low effort', () => {
  const row = scorePullRequest({
    ...base,
    labels: ['bug-fix'],
    mergeable: 'CONFLICTING',
    commitCount: 1,
  });
  assert.equal(row.disposition, 'merge-track');
});

test('eight commits without a keep label is salvage', () => {
  const row = scorePullRequest({ ...base, commitCount: 8, mergeable: 'CONFLICTING' });
  assert.equal(row.disposition, 'salvage');
});

test('six commits plus three comments is salvage', () => {
  const row = scorePullRequest({ ...base, commitCount: 6, issueComments: 2, reviewCount: 1 });
  assert.equal(row.disposition, 'salvage');
});

test('thin stale conflicting PR is a close candidate', () => {
  const row = scorePullRequest({
    ...base,
    commitCount: 2,
    mergeable: 'CONFLICTING',
    updatedAt: '2026-10-01T00:00:00Z',
  });
  assert.equal(row.disposition, 'close-candidate');
});

test('recent thin PR without conflict is not closed', () => {
  const row = scorePullRequest({ ...base, commitCount: 2 });
  assert.equal(row.disposition, 'review-then-decide');
});
```

- [ ] **Step 2: Run the test and confirm it fails**

Run from repo root: `node --test scripts/pr-portfolio/score.test.mjs`

Expected: fail because `score.mjs` does not exist.

- [ ] **Step 3: Implement the scorer**

```js
const KEEP_LABELS = new Set(['chances-high', 'fix', 'bugfix', 'bug-fix']);
const DAY_MS = 24 * 60 * 60 * 1000;

export function scorePullRequest(pr) {
  const labels = pr.labels || [];
  const keep = labels.some((name) => KEEP_LABELS.has(name));
  const discussion = (pr.issueComments || 0) + (pr.reviewCount || 0);
  const updated = Date.parse(pr.updatedAt);
  const now = Date.parse(pr.now);
  const ageDays = Number.isFinite(updated) && Number.isFinite(now)
    ? (now - updated) / DAY_MS
    : 0;
  const reasons = [];

  let disposition;
  if (keep) {
    disposition = 'merge-track';
    reasons.push('keep-merge label');
  } else if (pr.commitCount >= 8 || (pr.commitCount >= 6 && discussion >= 3)) {
    disposition = 'salvage';
    reasons.push('commit and discussion effort');
  } else if (
    pr.commitCount <= 3 &&
    (pr.issueComments || 0) === 0 &&
    (pr.reviewCount || 0) === 0 &&
    (pr.unresolvedThreads || 0) === 0 &&
    (pr.mergeable === 'CONFLICTING' || ageDays > 21)
  ) {
    disposition = 'close-candidate';
    reasons.push(pr.mergeable === 'CONFLICTING' ? 'thin and conflicting' : 'thin and stale');
  } else {
    disposition = 'review-then-decide';
    reasons.push('no keep label and effort below salvage');
  }

  if (pr.mergeable === 'CONFLICTING') {
    reasons.push('merge conflicts');
  }
  if ((pr.unresolvedThreads || 0) > 0) {
    reasons.push(`${pr.unresolvedThreads} unresolved review threads`);
  }

  return {
    number: pr.number,
    disposition,
    reasons,
    signals: {
      commitCount: pr.commitCount,
      issueComments: pr.issueComments || 0,
      reviewCount: pr.reviewCount || 0,
      resolvedThreads: pr.resolvedThreads || 0,
      unresolvedThreads: pr.unresolvedThreads || 0,
      ageDays: Math.round(ageDays),
      mergeable: pr.mergeable,
    },
  };
}

export function scoreAll(list) {
  return list.map(scorePullRequest);
}
```

- [ ] **Step 4: Re-run the test**

Run: `node --test scripts/pr-portfolio/score.test.mjs`

Expected: 5 passing tests.

- [ ] **Step 5: Commit**

```bash
git add scripts/pr-portfolio/score.mjs scripts/pr-portfolio/score.test.mjs
git commit -m "Add PR portfolio disposition scorer"
```

---

### Task 2: Inventory collector

**Files:**
- Create: `scripts/pr-portfolio/inventory.mjs`

**Interfaces:**
- Consumes: `gh api graphql` against `owner/repo` (default parsed from `git remote get-url origin`).
- Produces: JSON array written to the path in `argv[2]`, each element:

```js
{
  number, title, url, isDraft, createdAt, updatedAt,
  additions, deletions, changedFiles, mergeable, reviewDecision,
  labels: string[],
  commitCount, issueComments, reviewCount,
  resolvedThreads, unresolvedThreads,
  reviewStates: { APPROVED: number, CHANGES_REQUESTED: number, COMMENTED: number },
  latestReviewState: string | null
}
```

- [ ] **Step 1: Add the collector**

Page with `first: 15` until `pageInfo.hasNextPage` is false. Request `reviewThreads(first: 100)` and count `isResolved`. If `reviewThreads.totalCount > nodes.length`, set `threadCountTruncated: true` on that PR instead of silently under-counting. Deduplicate by PR number.

Query body (variables `$cursor`):

```graphql
query($cursor: String) {
  repository(owner: "OWNER", name: "REPO") {
    pullRequests(states: OPEN, first: 15, after: $cursor, orderBy: {field: UPDATED_AT, direction: DESC}) {
      pageInfo { hasNextPage endCursor }
      nodes {
        number title url isDraft createdAt updatedAt
        additions deletions changedFiles mergeable reviewDecision
        labels(first: 20) { nodes { name } }
        commits { totalCount }
        comments { totalCount }
        reviews(first: 50) {
          totalCount
          nodes { state submittedAt }
        }
        reviewThreads(first: 100) {
          totalCount
          nodes { isResolved }
        }
      }
    }
  }
}
```

Invoke with `gh api graphql -f query=... -f cursor=...`. On a non-zero exit, print stderr and exit 1. Do not retry more than 3 times on `API rate limit` (sleep 5s between tries).

- [ ] **Step 2: Smoke the collector**

Run: `node scripts/pr-portfolio/inventory.mjs .artifacts/pr-portfolio/inventory.json`

Expected: file exists, `JSON.parse` length equals `gh pr list --state open --limit 200 --json number --jq length`, and every row has numeric `commitCount`, `resolvedThreads`, and `unresolvedThreads`. `.artifacts/` is gitignored; do not commit the JSON.

- [ ] **Step 3: Commit**

```bash
git add scripts/pr-portfolio/inventory.mjs
git commit -m "Collect open PR inventory for portfolio disposition"
```

---

### Task 3: Report renderer and Make target

**Files:**
- Create: `scripts/pr-portfolio/report.mjs`
- Modify: `Makefile` (add target near other developer targets)

**Interfaces:**
- Consumes: inventory JSON from Task 2, `scoreAll` from Task 1.
- Produces: `.artifacts/pr-portfolio/disposition.json` and `.artifacts/pr-portfolio/disposition.md`.

- [ ] **Step 1: Render four sections**

`report.mjs` reads the inventory path from `argv[2]`, stamps `generatedAt` with `new Date().toISOString()`, calls `scoreAll` after attaching `now: generatedAt`, and writes:

- JSON: `{ generatedAt, disclaimer, counts, rows }` where `rows` are score objects plus `title`, `url`, `labels`, `mergeable`.
- Markdown: header disclaimer (“This ranking is not merge approval.”), a counts table, then one section per disposition in order `merge-track`, `salvage`, `close-candidate`, `review-then-decide`. Each PR line includes number, title, commits, issue comments, reviews, resolved/unresolved threads, mergeable, labels, and reasons.

Sort inside each section by unresolved threads descending, then commit count descending.

- [ ] **Step 2: Make target**

```make
pr-portfolio:
	mkdir -p .artifacts/pr-portfolio
	node scripts/pr-portfolio/inventory.mjs .artifacts/pr-portfolio/inventory.json
	node scripts/pr-portfolio/report.mjs .artifacts/pr-portfolio/inventory.json
```

- [ ] **Step 3: Generate once and sanity-check against the 2026-11-28 baseline**

Run: `make pr-portfolio`

Expected counts in the same neighborhood as the baseline (open set may have moved): on the order of 11 `merge-track` rows, high-teens `salvage`, and a non-zero `close-candidate` set. Known anchors if they are still open:

- `#2448` and `#1502` are `merge-track` (`fix` / `bug-fix` / `chances-high`).
- `#2413` (23 commits, 19 reviews, unresolved threads) is `salvage`.
- `#1877` (56 commits) is `salvage` even though it conflicts and has no keep-merge label.

If an anchor has merged or closed, skip it; do not hard-code numbers into the scorer.

- [ ] **Step 4: Point the portfolio workflow at the artifact**

In `.claude/workflows/pr-portfolio-triage.md` Phase 1, add one line: collect the open set by running `make pr-portfolio` and read `.artifacts/pr-portfolio/disposition.md` before ranking. Leave Phases 2–4 unchanged.

- [ ] **Step 5: Commit**

```bash
git add scripts/pr-portfolio/report.mjs Makefile .claude/workflows/pr-portfolio-triage.md
git commit -m "Render PR portfolio disposition report"
```

---

### Task 4: Use the report; do not auto-close

The report is the input to a human merge/close pass. Execution of this task is a written recommendation list, not `gh pr close`.

- [ ] **Step 1: merge-track order**

List `merge-track` PRs that are `MERGEABLE` and have zero unresolved threads first. Those are the only rows eligible to be called “ready to merge” after CI is green. Conflicting `merge-track` rows get a rebase task, not a close recommendation.

- [ ] **Step 2: salvage order**

Group `salvage` rows that touch the same subsystem (generation, history, admin UI, notifications, dev tooling) before recommending a merge sequence. Overlap detection stays in the portfolio-triage workflow; this script does not diff files in v1.

- [ ] **Step 3: close list**

Publish `close-candidate` numbers, titles, and the reason string. Closing still requires an explicit human confirmation per PR.

## Self-review

- Spec coverage: labels `chances-high` / `fix` / `bugfix` map to rule 1, including the live `bug-fix` alias. Commit count, issue comments, review rounds, resolved threads, and unresolved threads are inventory fields and appear on every report row. Conflict plus high effort stays `salvage`, not close.
- Out of scope for v1: file-level overlap, CI rollup, and automatic close/merge. Those remain in `.claude/workflows/pr-portfolio-triage.md` and human review.
