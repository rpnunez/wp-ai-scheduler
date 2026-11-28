# PR triage reports

Read-only tooling that measures every open PR and proposes a merge/close disposition.
Requires Python 3.9+, `git` 2.38+ (for `merge-tree --write-tree`), and an authenticated `gh` CLI.

```bash
python scripts/pr-triage/collect.py    # GitHub + git metrics -> .artifacts/pr-triage/prs.{json,csv}
python scripts/pr-triage/classify.py   # scores, buckets, reports -> .artifacts/pr-triage/*.md, triage.{json,csv}
```

`collect.py --prs 2448,2450` limits the run to specific PRs; `--skip-git` skips local conflict analysis.
Outputs land in the gitignored `.artifacts/pr-triage/`. PR heads are fetched into `refs/pr-triage/*`
(remove with `git for-each-ref --format='%(refname)' refs/pr-triage | xargs -n1 git update-ref -d`).

## Metrics

| Metric | Meaning |
|---|---|
| `commits`, `commit_authors`, `commit_span_days` | Effort invested in the branch |
| `review_rounds` | Distinct head commits that received a review. Formal GitHub reviews **and** agent reviews posted as PR comments both count, bots included (`review_rounds_formal`, `_human`, `_bot`, `_comment` break it down) |
| `reviews_*` | Formal reviews by state (approved / changes requested / commented) |
| `threads`, `threads_resolved`, `threads_unresolved`, `threads_unresolved_active` | Inline review threads; "active" = unresolved and not outdated |
| `comments_human` / `comments_bot`, `review_comments_agent`, `review_triggers` | Conversation activity |
| `behind_by`, `ahead_by`, `merge_base_date` | Freshness vs `main` |
| `merge_clean`, `conflict_files_count`, `conflict_hunks`, `conflict_types` | Real conflicts from `git merge-tree` against current `main` |
| `pr_files_deleted_in_main`, `pr_files_changed_in_main`, `main_commits_touching_pr_files` | How far `main` moved under the PR |
| `ci_state`, `force_pushes`, `review_requests` | Supporting signals |

## Policy (edit constants at the top of `classify.py`)

- **A – mandatory keep:** label `chances-high`, `fix`, `bugfix`, or `bug-fix`.
- **D – possible duplicate:** high file-set overlap (or similar title + overlap) with a stronger open PR. Suggest `chances-low`.
- **B – merge now:** merges clean, not draft, CI not failing, no active unresolved threads.
- **C – high-effort keep:** ≥ 5 commits **or** ≥ 2 review rounds.
- **E – close candidate:** low effort and heavy cost (≥ 8 conflict files, ≥ 300 behind with conflicts, or files deleted in `main`).
- **F – needs review:** everything else. Suggest `chances-low` (pending, needs review).

Effort score (0–100) weighs commits, review rounds, resolved threads, discussion, and participants.
Cost score (0–100) weighs conflicts, behind-by, files deleted in `main`, active threads, failing CI, and size.
The scripts never label, close, or merge anything; the triage report lists proposed actions for sign-off.
