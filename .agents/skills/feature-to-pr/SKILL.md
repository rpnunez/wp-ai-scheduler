---
name: feature-to-pr
description: Deliver an explicitly requested wp-ai-scheduler feature or bug fix from planning and approval through implementation, targeted testing, PR creation, full branch review, fixes, and final PR updates. Use when the user invokes $feature-to-pr or asks for this complete delivery workflow.
---

# Feature to PR

Use this skill for the complete delivery workflow requested by the user. The user's task defines the change; repository instructions define implementation and testing standards. Read the root `AGENTS.md`, `TESTING.md`, and relevant project documentation before editing.

## 1. Scoping, Research, and Implementation Plan (Approval Required)

- Inspect Git status, current branch, remotes, and the repository's default/base branch. Preserve unrelated user changes. If the checkout is dirty, use an isolated worktree or another safe route rather than moving or overwriting those changes.
- Fetch and verify the current base ref. **Do not create the dedicated branch yet.**
- Inspect affected code, existing tests, hooks, and runtime paths on the base ref.
- Present a structured **Implementation Plan** directly in the chat with the following mandatory sections:
  1. **Technical Approach & Target Files**: Classes, hooks, templates, settings, or configs to modify or create.
  2. **Test Strategy**: Specific test files to create or run via `make test-session ARGS="..."` and mock expectations.
  3. **Risks & Safety Checks**: Database schema/migrations, backward compatibility, nonce/capability checks, and Meow Apps AI Engine dependency handling.
  4. **Step-by-Step Task Checklist**: Ordered implementation milestones to be checked off during execution.
- **STOP and wait for explicit user approval.** Do not create the dedicated Git branch or modify any files until the user approves the plan. If the user provides feedback or requests changes, adjust the plan and re-confirm.

## 2. Branch Creation and Implementation

- Once the plan is approved, create a dedicated, descriptively named branch from the verified base ref (do not assume `main` or `master` without checking).
- Follow `AGENTS.md` and applicable subsystem documentation. Keep changes strictly scoped to the approved plan and preserve data, migrations, compatibility, and existing integrations.
- Adhere to project conventions: tabs, `array()` syntax, `AIPS_` prefix, singleton container lookups (`AIPS_Container::get_instance()->make(...)`), and no raw `$wpdb` outside repository classes.
- Update documentation in `docs/` or `CHANGELOG.md` when behavior or APIs change.

## 3. Testing and Verification

- **Tier 1: Fast Static & Syntax Checks**:
  - Run syntax checks (`php -l`) on all touched PHP files before running Docker or full tests to catch syntax errors immediately.
- **Tier 2: Targeted Test Execution**:
  - Add or update meaningful tests for changed behavior.
  - Follow `TESTING.md` exactly for PHPUnit: run targeted tests for the touched code via `make test-session ARGS="tests/Test_A.php tests/Test_B.php"` from the repository root.
  - Full test suite runs are not required on every delivery, but targeted tests covering touched code are mandatory.
  - Respect Docker ownership and cleanup rules from `TESTING.md`: stop the stack if you started it (`make test-session` handles this).
- **Docker Unavailable Fallback**:
  - If Docker is unavailable or cannot run, immediately inform the user in chat.
  - Continue to proceed with the PR workflow, but explicitly state in chat and in the PR description that Docker was unavailable and automated tests could not be executed locally.

## 4. Open the Pull Request

- Inspect the diff for accidental files, secrets, debug code, whitespace errors, and changes outside scope.
- Commit the implementation in coherent commit(s) and push the branch.
- Open a PR against the verified base branch using the dedicated template at `.agents/skills/feature-to-pr/resources/pull_request_template.md`.
- Fill out all template sections: Problem & Solution summary, Approved Plan highlights, exact verification results (including Docker status), and applicable risk labels (e.g. `schema-change`, `ajax-registry`, `security-sensitive`).

## 5. Branch Review and Self-Correction

- Review the complete branch changeset against the base branch using the following 5 checklists:
  1. **Security & Permissions**: Verify proper escaping (`esc_html`, `esc_attr`, `wp_kses_post`), input sanitization, nonce verification (`check_ajax_referer` / `wp_verify_nonce`), capability checks (`current_user_can('manage_options')`), and boolean AJAX parameter parsing (`filter_var($val, FILTER_VALIDATE_BOOLEAN)`).
  2. **Architectural Integrity**: Ensure no raw `$wpdb` calls outside repositories, proper DI container usage, AJAX registered in `AIPS_Ajax_Registry`, and graceful handling when Meow Apps AI Engine is missing or inactive.
  3. **Hygiene & Cleanliness**: Check for tabs indentation, `array()` syntax, no lingering `var_dump`/`error_log`, clean comments, and removal of any temporary scratch files.
  4. **Data & Backward Compatibility**: Ensure DB migrations are idempotent, existing options/settings are preserved, and relational mappings are cloned cleanly.
  5. **Performance & Query Optimization**: Check for N+1 query patterns and consider transient caching where applicable.
- **Fix Plan**:
  - Record each actionable finding with file/line, failure scenario, and severity.
  - Formulate a concise fix plan. For straightforward bug/lint fixes, proceed autonomously. If a finding requires significant architectural changes or altered behavior, pause and ask for user approval first.
  - Implement fixes, re-run targeted checks, and ensure no regressions. If no findings are found, state that plainly without inventing fixes.

## 6. Finalize the PR

- Commit and push review fixes, if any.
- Update the PR description to reflect the final branch state, test verification details, and resolved review findings.
- Post one PR comment listing review findings and their resolutions (or noting that the branch review found no issues).
- Check PR status and CI after final push when available.
- Report the PR URL, branch name, commits, verification results, review outcome, and remaining blockers to the user. Do not merge unless explicitly requested.

## Stop Conditions

Do not silently skip a stage. If authentication, permission, CI, or an irreconcilable ambiguity prevents completion, complete all independent work, state the exact blocker and stage, and report what remains. Never claim tests passed, reviews were clean, or PR updates were posted unless verified.
