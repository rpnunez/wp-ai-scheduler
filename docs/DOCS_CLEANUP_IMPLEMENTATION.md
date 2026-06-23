# Documentation Cleanup — Implementation Plan

Step-by-step execution guide for the repository documentation cleanup. Follow phases in order; each phase is independently committable.

---

## Pre-flight

```bash
git checkout claude/docs-cleanup-plan-gdjevw
git status --short
```

Confirm working tree is clean before starting.

---

## Phase 1 — Inventory and classification

**Purpose:** Verify the file list matches expectations before any deletions.

```bash
find docs/features docs/plans docs/mcp -type f | sort
```

Expected inventory (based on current state):

### `docs/features/`

| File | Decision | Reason |
|------|----------|--------|
| `docs/features/ai-edit/docs.md` | **Migrate** | Content is already correct ("AI Assistance"); rename + move to Phase 3 |
| `docs/features/ai-edit/AI_EDIT_USER_GUIDE.md` | **Migrate** | Rename and update content in Phase 3 |
| `docs/features/ai-edit/AI_EDIT_REVISION_VIEWER.md` | **Migrate** | Rename and update content in Phase 3 |
| `docs/features/article-structures/ARTICLE_STRUCTURES_DOCUMENTATION.md` | **Delete** | Historical feature doc; not referenced by canonical docs |
| `docs/features/authors/` (all 12 files) | **Delete** | Redundant with `docs/AI_AGENT_REFERENCE.md`; implementation journals and expanded examples |
| `docs/features/export-session/COPY_SESSION_JSON_FEATURE.md` | **Delete** | Narrow implementation note; not canonical |
| `docs/features/generated-posts/GENERATED_POSTS_FEATURE.md` | **Delete** | Superseded by canonical docs |
| `docs/features/generated-posts/POST_REVIEW_FEATURE.md` | **Delete** | Historical |
| `docs/features/generated-posts/SOURCE_COLUMN_IMPLEMENTATION.md` | **Delete** | Implementation journal |
| `docs/features/generated-posts/post_partial_generation_regeneration_post_save.md` | **Delete** | Implementation journal; references old AI Edit names |
| `docs/features/history/HISTORY_FEATURE_ANALYSIS.md` | **Delete** | Historical analysis |
| `docs/features/history/HISTORY_FEATURE_DOCUMENTATION.md` | **Delete** | Historical |
| `docs/features/history/HISTORY_FEATURE_FLOWCHARTS.md` | **Delete** | Historical |
| `docs/features/research/` (all 6 files) | **Delete** | Old research subsystem docs; superseded |
| `docs/features/templates/PR-1729-DEVSTACKTIPS-SEEDER-REVIEW.md` | **Delete** | PR review artifact |
| `docs/features/templates/PREVIEW_DRAWER_ARCHITECTURE.md` | **Delete** | Implementation journal |
| `docs/features/templates/TRENDING_TOPICS.md` | **Delete** | Historical feature doc |

### `docs/plans/`

| File | Decision |
|------|----------|
| `docs/plans/cache-monitor/plan.md` | **Delete** — completed work |
| `docs/plans/repository-cache-framework/plan.md` | **Delete** — completed work |
| `docs/plans/repository-cache-framework/migration-away-from-legacy-classes-plan.md` | **Delete** — completed work |
| `docs/plans/sources-v2/plan.md` | **Delete** — completed work |
| `docs/plans/standardize-datetime/DB_SCHEMA.md` | **Review** — extract any schema facts not in `docs/MIGRATIONS.md`, then delete |
| `docs/plans/standardize-datetime/FINDINGS.md` | **Review** — extract any DateTime conventions not in `docs/DEVELOPMENT_GUIDELINES.md`, then delete |
| `docs/plans/standardize-datetime/plan.md` | **Delete** — completed work |

### `docs/mcp/`

| File | Decision |
|------|----------|
| `docs/mcp/MCP_BRIDGE_README.md` | **Review** — check if MCP bridge is still maintained; if yes, becomes `docs/MCP_BRIDGE.md` |
| `docs/mcp/MCP_BRIDGE_QUICKSTART.md` | **Merge** into single doc if maintained, else delete |
| `docs/mcp/MCP_BRIDGE_INTEGRATION.md` | **Merge** or delete |
| `docs/mcp/MCP_BRIDGE_CONTENT_TOOLS.md` | **Delete** — phase-era doc |
| `docs/mcp/MCP_BRIDGE_PHASE2_TOOLS.md` | **Delete** — phase-era doc |
| `docs/mcp/MCP_BRIDGE_PHASE3_TOOLS.md` | **Delete** — phase-era doc |
| `docs/mcp/MCP_BRIDGE_VSCODE_SETUP.md` | **Merge** or delete |

### Root dev docs

| File | Decision |
|------|----------|
| `docs/DEV.md` | **Merge** unique content into `docs/DEVELOPMENT_GUIDELINES.md`, then delete |
| `docs/DEV_HANDBOOK.md` | **Merge** unique content into `docs/DEVELOPMENT_GUIDELINES.md`, then delete |
| `docs/DEVELOPMENT_GUIDELINES.md` | **Keep** — canonical |
| `docs/RUNBOOK.md` | **Keep** if operationally current; delete stale sections |
| `docs/PERFORMANCE.md` | **Keep** if actively maintained; delete otherwise |
| `docs/Design_Guidelines.md` | **Keep** only if it documents current UI/design standards; check content |
| `docs/SETUP.md` | **Keep** — canonical |
| `docs/FEATURES.MD` | **Review** — check if superseded by `docs/FEATURE_LIST.md` (note: `AGENTS.md` references `docs/FEATURE_LIST.md`) |

Also review and classify:
- `docs/feature-report.md` — generated inventory artifact, likely **Delete**
- `docs/feature-report-feature-profiles.md` — generated inventory artifact, likely **Delete**
- `docs/inventory/ajax-callbacks-inventory.md` — generated inventory, likely **Delete**
- `docs/inventory/ajax-callbacks-inventory-work.md` — generated inventory, likely **Delete**
- `docs/run-devstacktools-content-script.md` — script note, likely **Delete**
- `docs/sql-extraction-backlog.md` — backlog artifact, likely **Delete**

---

## Phase 2 — Prune stale `docs/features/` content (excluding `ai-edit/`)

**Do Phase 3 first, then return to delete the old `ai-edit/` folder here.**

Delete all stale feature sub-folders and files classified above. One commit per logical group.

```bash
# Article structures
rm docs/features/article-structures/ARTICLE_STRUCTURES_DOCUMENTATION.md
rmdir docs/features/article-structures

# Authors
rm -r docs/features/authors

# Export session
rm docs/features/export-session/COPY_SESSION_JSON_FEATURE.md
rmdir docs/features/export-session

# Generated posts
rm -r docs/features/generated-posts

# History
rm -r docs/features/history

# Research
rm -r docs/features/research

# Templates
rm -r docs/features/templates
```

Before deleting `docs/features/generated-posts/post_partial_generation_regeneration_post_save.md`, scan it for any still-current behavior:

```bash
grep -i "aips_\|class-\|table\|schema" docs/features/generated-posts/post_partial_generation_regeneration_post_save.md
```

If any architectural facts are not captured in `docs/AI_AGENT_REFERENCE.md`, migrate them first.

Commit message: `docs: remove stale feature sub-folder documentation`

---

## Phase 3 — Rename and update `docs/features/ai-edit/` to `docs/features/ai-assistance/`

This phase does **not** delete the folder — it brings it up to date by renaming the folder, renaming each file, and updating outdated naming in the content.

### Step 3.1 — Create the new folder

```bash
mkdir docs/features/ai-assistance
```

### Step 3.2 — Migrate `docs.md` → `AI_ASSISTANCE_DEV_GUIDE.md`

The content of `docs.md` is **already correct** — it uses "AI Assistance" throughout and describes the current `AIPS_AI_Assistance_*` classes and `aips_ai_assistance` table. Only the filename needs to change.

```bash
cp docs/features/ai-edit/docs.md docs/features/ai-assistance/AI_ASSISTANCE_DEV_GUIDE.md
```

No content edits required beyond confirming the title reads `# AI Assistance — Developer Integration Guide`.

### Step 3.3 — Migrate `AI_EDIT_USER_GUIDE.md` → `AI_ASSISTANCE_USER_GUIDE.md`

```bash
cp docs/features/ai-edit/AI_EDIT_USER_GUIDE.md docs/features/ai-assistance/AI_ASSISTANCE_USER_GUIDE.md
```

Then apply the following content updates to `docs/features/ai-assistance/AI_ASSISTANCE_USER_GUIDE.md`:

**Title and heading changes:**
- `# AI Edit Feature - User Guide` → `# AI Assistance — User Guide`
- Any H2/H3 that says "AI Edit" (e.g. "Accessing AI Edit", "Using the Modal") → update to "AI Assistance"

**Body text changes:**
- Opening paragraph: replace "The AI Edit feature allows you to regenerate individual components…" with "The AI Assistance feature allows you to regenerate individual components…"
- All instances of "AI Edit" in running prose → "AI Assistance"
- "Click the "AI Edit" button" → "Click the **AI Assistance** button"

**AJAX actions — keep as-is with a legacy note:**
Under `### AJAX Endpoints`, add a sentence above the list:

> **Note:** These AJAX action names use the legacy `aips_*_component*` convention. They remain unchanged for runtime compatibility.

Do not rename the AJAX action strings in the doc; they reflect the actual registered action names in the PHP code.

**Footer metadata:**
- Remove or update the hardcoded `Last Updated: 2026-02-09` line — either drop it or replace with a note that the file tracks the feature as of the version it was last verified.

### Step 3.4 — Migrate `AI_EDIT_REVISION_VIEWER.md` → `AI_ASSISTANCE_REVISION_VIEWER.md`

```bash
cp docs/features/ai-edit/AI_EDIT_REVISION_VIEWER.md docs/features/ai-assistance/AI_ASSISTANCE_REVISION_VIEWER.md
```

Apply the following content updates:

**Title and heading changes:**
- `# AI Edit Revision Viewer - Implementation Documentation` → `# AI Assistance — Revision Viewer`
- All H2/H3 headings that say "AI Edit" → "AI Assistance"

**Body text changes:**
- All prose references to "AI Edit modal" → "AI Assistance modal"
- "AI Edit feature" → "AI Assistance feature"

**File path references — add legacy notes:**
The doc references specific runtime files:
- `assets/css/admin-ai-edit.css`
- `assets/js/admin-ai-edit.js`
- `includes/class-aips-ai-edit-controller.php`

These file names have **not** been renamed (this cleanup is documentation-only). Add a callout block above the "Related Files" footer:

> **Compatibility note:** The runtime files below retain their original `ai-edit` filenames. These names are legacy identifiers in the codebase and will be updated in a separate refactoring pass.

**AJAX action names:**
- `aips_get_component_revisions`, `aips_restore_component_revision` — keep as-is; add the same compatibility note used in Step 3.3.

**Footer `Related Files`:**
Keep the listed paths verbatim (they are real current paths), with the compatibility callout above.

### Step 3.5 — Delete the old `docs/features/ai-edit/` folder

After confirming the three new files in `docs/features/ai-assistance/` are correct:

```bash
rm -r docs/features/ai-edit
```

### Step 3.6 — Verify no broken references

```bash
rg -rn "docs/features/ai-edit\|ai-edit/" docs README.md AGENTS.md .github
```

Update any links that pointed to the old path.

Commit message: `docs: rename ai-edit folder to ai-assistance and update content`

---

## Phase 4 — Remove stale `docs/plans/`

### Step 4.1 — Review `standardize-datetime/` for migratable facts

Before deleting, scan for content not already in canonical docs:

```bash
grep -i "datetime\|timestamp\|created_at\|bigint\|unix" \
  docs/plans/standardize-datetime/DB_SCHEMA.md \
  docs/plans/standardize-datetime/FINDINGS.md
```

If the DateTime conventions (e.g. `AIPS_DateTime::fromTimestampOrNull()`, `created_at` as bigint Unix timestamp) are not yet documented in `docs/DEVELOPMENT_GUIDELINES.md`, add a short "Date and Time Handling" section there before deleting.

Cross-check: `docs/features/ai-edit/docs.md` (and the new `AI_ASSISTANCE_DEV_GUIDE.md`) already contains:

> `created_at` stores a Unix timestamp (bigint) aligned with the DateTime refactor; use `AIPS_DateTime::fromTimestampOrNull()` to convert to display format.

If that is sufficient, no further migration is needed.

### Step 4.2 — Delete all plans

```bash
rm -r docs/plans
```

Commit message: `docs: remove completed implementation plans`

---

## Phase 5 — Consolidate or delete `docs/mcp/`

### Step 5.1 — Determine MCP bridge status

Check whether `MCP_BRIDGE_README.md` describes a currently maintained workflow:

```bash
cat docs/mcp/MCP_BRIDGE_README.md
grep -r "mcp\|MCP" AGENTS.md docs/DEVELOPMENT_GUIDELINES.md docs/SETUP.md
```

**If MCP bridge is no longer maintained:**

```bash
rm -r docs/mcp
```

Commit message: `docs: remove obsolete MCP bridge documentation`

**If MCP bridge is still maintained:**

1. Create `docs/MCP_BRIDGE.md` by merging the essential content from:
   - `MCP_BRIDGE_README.md` (overview + purpose)
   - `MCP_BRIDGE_QUICKSTART.md` (setup steps)
   - `MCP_BRIDGE_INTEGRATION.md` (integration details)
   - `MCP_BRIDGE_VSCODE_SETUP.md` (editor config)
2. Discard `MCP_BRIDGE_CONTENT_TOOLS.md`, `MCP_BRIDGE_PHASE2_TOOLS.md`, `MCP_BRIDGE_PHASE3_TOOLS.md` — phase artifacts.
3. Add a link from `docs/SETUP.md` to `docs/MCP_BRIDGE.md`.
4. Delete `docs/mcp/`.

Commit message: `docs: consolidate MCP bridge docs into single reference`

---

## Phase 6 — Consolidate overlapping root dev docs

### Step 6.1 — Merge `docs/DEV.md` and `docs/DEV_HANDBOOK.md` into `docs/DEVELOPMENT_GUIDELINES.md`

`docs/DEV.md` (375 lines) and `docs/DEV_HANDBOOK.md` (105 lines) overlap significantly with `docs/DEVELOPMENT_GUIDELINES.md`.

Process:
1. Read `docs/DEV.md` and `docs/DEV_HANDBOOK.md` fully.
2. For each section, ask: is this content already in `docs/DEVELOPMENT_GUIDELINES.md` or `AGENTS.md`? If not and it is still current, add it to the appropriate section of `docs/DEVELOPMENT_GUIDELINES.md`.
3. Common candidates to migrate: Docker setup specifics, Makefile targets, local debug flags, test runner commands, script inventory.
4. After migration, delete both files:

```bash
rm docs/DEV.md docs/DEV_HANDBOOK.md
```

### Step 6.2 — Review and trim `docs/RUNBOOK.md`

`docs/RUNBOOK.md` (139 lines) covers queue and generation incident handling. Keep if it describes current operational procedures. Delete sections that describe removed features or superseded workflows. If the file becomes trivially small after trimming, merge its contents into `docs/DEVELOPMENT_GUIDELINES.md` or `docs/SETUP.md` and delete it.

### Step 6.3 — Review `docs/PERFORMANCE.md`

`docs/PERFORMANCE.md` (210 lines) covers the performance benchmarking system. If CI/CD benchmarking is still active, keep the file. If the benchmarking system is removed or the CI configuration no longer references it, delete the file.

```bash
grep -r "benchmark\|performance" .github/workflows/ 2>/dev/null | head -20
```

### Step 6.4 — Review `docs/Design_Guidelines.md`

Check whether it describes current UI standards still in use. If it references removed UI patterns or is entirely generic, delete it.

### Step 6.5 — Reconcile `docs/FEATURES.MD` vs `docs/FEATURE_LIST.md`

`AGENTS.md` references `docs/FEATURE_LIST.md` but the file on disk is `docs/FEATURES.MD`. Resolve the discrepancy:

```bash
ls docs/FEAT*
grep "FEATURE_LIST\|FEATURES.MD" AGENTS.md
```

Options:
- If `docs/FEATURES.MD` is the canonical file, rename it to `docs/FEATURE_LIST.md` and update `AGENTS.md`.
- If a separate `docs/FEATURE_LIST.md` exists somewhere, consolidate and remove `FEATURES.MD`.

### Step 6.6 — Delete loose inventory and artifact docs

```bash
rm docs/feature-report.md
rm docs/feature-report-feature-profiles.md
rm docs/inventory/ajax-callbacks-inventory.md
rm docs/inventory/ajax-callbacks-inventory-work.md
rmdir docs/inventory
rm docs/run-devstacktools-content-script.md
rm docs/sql-extraction-backlog.md
```

Confirm none are linked from `AGENTS.md`, `README.md`, or canonical docs before deleting.

Commit message: `docs: consolidate root dev docs and remove loose artifacts`

---

## Phase 7 — Verification

Run after all phases are committed:

```bash
# 1. Confirm deleted folders are gone
find docs/features docs/plans docs/mcp -type f 2>/dev/null | sort

# 2. Confirm new ai-assistance folder exists with correct files
find docs/features/ai-assistance -type f | sort

# 3. No stale "AI Edit" references in canonical docs (only runtime compat notes allowed)
rg -n "AI Edit|ai-edit|AI_EDIT" docs README.md AGENTS.md

# 4. No broken links to deleted paths in key docs
rg -n "DEV_HANDBOOK|docs/DEV\.md|docs/features/|docs/plans/|docs/mcp/" \
  README.md AGENTS.md docs .github

# 5. Git status shows only documentation changes
git status --short
git diff --stat HEAD
```

**Expected results:**

- `docs/features/ai-edit/` does not appear; `docs/features/ai-assistance/` exists with three files.
- `docs/plans/` is absent.
- `docs/mcp/` is absent or replaced by `docs/MCP_BRIDGE.md`.
- `docs/DEV.md` and `docs/DEV_HANDBOOK.md` are absent.
- "AI Edit" appears only in runtime compatibility notes within the `ai-assistance/` docs.
- No canonical doc links to a deleted file.
- `git diff --stat` shows only `.md` file changes.

---

## Commit strategy

| Phase | Suggested commit message |
|-------|--------------------------|
| 2 | `docs: remove stale feature sub-folder documentation` |
| 3 | `docs: rename ai-edit folder to ai-assistance and update content` |
| 4 | `docs: remove completed implementation plans` |
| 5 | `docs: remove (or consolidate) MCP bridge documentation` |
| 6 | `docs: consolidate root dev docs and remove loose artifacts` |

Squash into one commit before merging if preferred; keep as separate commits for easier review.
