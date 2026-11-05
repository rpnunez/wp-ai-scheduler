---
name: feature-to-pr
description: Deliver an explicitly requested wp-ai-scheduler feature or bug fix from a fresh base branch through implementation, testing, PR creation, full branch review, fixes, and final PR updates. Use when the user invokes $feature-to-pr or asks for this complete delivery workflow.
---

# Feature to PR

Use this skill for the complete delivery workflow requested by the user. The user's task defines the change; repository instructions define implementation and testing standards. Read the root `AGENTS.md`, `TESTING.md`, and relevant project documentation before editing.

## 1. Establish scope and branch

- Inspect Git status, current branch, remotes, and the repository's default/base branch. Preserve unrelated user changes. If the checkout is dirty, use an isolated worktree or another safe route rather than moving or overwriting those changes.
- Fetch and verify the current base ref, then create a dedicated, descriptively named branch from it. Do not assume `main` or `master` without checking.
- Inspect the affected code and related runtime paths. Write a concise implementation plan with the intended changes, verification, and material risks. Continue into implementation under the user's full-workflow request; seek input only for a material product choice or a genuine blocker.

## 2. Implement and verify

- Follow `AGENTS.md` and applicable subsystem documentation. Keep changes scoped to the request and preserve data, migrations, compatibility, and existing integrations.
- Add or update meaningful tests for changed behavior. Follow `TESTING.md` exactly for PHPUnit: use its `make test-session` protocol from the repository root, batch test files in one invocation, and respect its Docker ownership and cleanup rules.
- Run focused checks, then the full suite before opening the PR as required by `TESTING.md`. Record the exact results. If a check cannot run or fails, investigate and distinguish a branch regression from a pre-existing baseline where practical; report unresolved limits accurately.
- Inspect the diff for accidental files, secrets, whitespace errors, and changes outside scope.

## 3. Open the PR

- Commit the implementation in coherent commit(s), push the branch, and open a PR against the verified base branch.
- The initial PR body must state the problem, solution, significant implementation details, tests and results, and any known limitations. Keep its claims aligned with observed evidence.

## 4. Review the entire branch and address findings

- Review the complete branch changeset against the base branch, including earlier commits, relevant callers, database effects, permissions, frontend/admin asset boundaries, compatibility, and tests. Prioritize correctness, security, data preservation, and regressions. Record each actionable finding with file/line, failure scenario, and severity.
- Make a concise fix plan covering all findings, then implement it. Add or adjust tests where they establish the fix. Run the relevant checks again, and repeat review of the updated branch until no actionable findings remain or a specific blocker is documented.
- Do not describe the review as independent or exhaustive. If no findings are found, say so plainly; do not invent findings or a fix commit.

## 5. Finalize the PR

- Commit and push review fixes, if any. Update the PR body to describe the final branch state and current verification results.
- Post one PR comment listing each review finding and its resolution, with commit references when useful. If there were no actionable findings, state that the branch review found none and summarize the areas checked.
- Check PR status and CI after the final push when available. Report the PR URL, branch, commits, verification results, review outcome, and remaining blockers to the user. Do not merge unless explicitly requested.

## Stop conditions

Do not silently skip a stage. If authentication, permission, CI, test environment, or a material ambiguity prevents completion, finish all independent work, identify the exact blocker and stage, and state what remains. Do not claim tests passed, review was clean, or PR updates were posted unless verified.
