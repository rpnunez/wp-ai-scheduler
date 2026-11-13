# CSS Token Consolidation Implementation Plan

**Goal:** Complete the outstanding stylesheet token migrations without making shared tokens unavailable to frontend or editor assets.

## Tasks

1. Extract the `:root` token contract from `assets/css/admin.css` into `assets/css/design-tokens.css`; enqueue it before `admin.css` and add it as a dependency wherever a stylesheet is loaded without `admin.css`.
2. Validate the existing Phase 4 changes in `admin-content-auditor.css` and `stress-test.css` through token/fallback scans and visual checks.
3. Migrate zero-adoption admin styles: telemetry, AI assistance, post slices, and post review.
4. Migrate zero-adoption standalone styles: background processes, internal-links panel, and related posts.
5. Complete partial migrations: link report, post insights, research, and planner; remove all fallback parameters from migrated styles.
6. Run static CSS audits, syntax checks, focused PHPUnit for touched PHP enqueue code, and visual smoke checks for each affected surface.

## Constraints

- Keep the shared token system as the sole source of `--aips-*` values.
- No `var(--aips-*, fallback)` in migrated stylesheets.
- Preserve frontend and native-editor behavior by loading the token asset explicitly.
- Use `make test-session` for PHPUnit commands.
