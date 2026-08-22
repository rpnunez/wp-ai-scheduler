# Remediation plan — PR #1948 (`feature/generated-post-feedback`) code review

Branch: `fix/generated-post-feedback-review-findings` (based on `feature/generated-post-feedback`).
Merge target: `feature/generated-post-feedback` (NOT `main`).

Ordered so each slice is independently testable. Slices 1–2 are release blockers.

---

## Slice 1 — Schema actually ships (blocker)

**Finding:** `ai-post-scheduler/ai-post-scheduler.php:47` — `AIPS_VERSION` is still `3.4.2`.
`AIPS_DB_Migrations::check_and_run()` gates on `version_compare($stored, AIPS_VERSION, '<')`, so on
every existing install dbDelta never runs: `wp_aips_post_feedback` is not created and
`feedback_enabled` / `feedback_config` are never added to `aips_templates` / `aips_authors`.

**Fix**
1. Bump `AIPS_VERSION` to `3.5.0` (new table + new columns = minor bump) at `ai-post-scheduler.php:47`.
2. Bump the matching `Version:` plugin header.
3. Add the 3.5.0 entry to `CHANGELOG.md` describing the feedback feature and the schema additions.
4. Add a guard in `AIPS_Post_Feedback_Repository` (and in
   `AIPS_Post_Review_Repository::get_generated_posts`) that no-ops when the feedback table is
   absent, mirroring the pattern used by the cache-index fix in `305e8de7`. This keeps a
   half-upgraded site from throwing DB errors on the Generated Posts tab.

**Verify:** `tests/Test_AIPS_Post_Feedback_Schema.php` should assert the table and both columns exist
after `install_tables()`; add an assertion that `AIPS_VERSION` is greater than the version in which
the feedback schema was introduced.

---

## Slice 2 — Template create loses feedback overrides (blocker)

**Finding:** `includes/class-aips-template-repository.php:197` — `feedback_enabled` /
`feedback_config` were added to `$insert_data` at positions 20–21 (before `created_at` /
`updated_at`) while their format specifiers were appended to the END of `$format`. `wpdb::insert`
maps formats positionally, so `feedback_config` (a JSON string) is bound as `%d` and stored as `0`,
and `updated_at` is bound as `%s`.

**Fix**
- Move `%d`, `%s` into `$format` at indices 19 and 20, i.e.
  `... is_active => %d, feedback_enabled => %d, feedback_config => %s, created_at => %d, updated_at => %d`.
- Better: replace the positional array with a `$formats` map keyed by column name and build the
  format list from `array_keys($insert_data)` so this class of bug cannot recur.

**Verify:** new test — create a template with `feedback_config` overrides via
`AIPS_Templates::create()`, reload with `AIPS_Template_Data::from_row()`, assert the overrides
round-trip. Extend `Test_AIPS_Post_Feedback_Scope_Persistence.php` to cover create as well as update.

---

## Slice 3 — Regeneration leaves dangling history rows

**Finding:** `includes/class-aips-post-review.php:439` and `:646` — both regeneration paths removed
the `update_history_record($history_id, ['status' => 'pending', 'post_id' => null])` call but still
hard-delete the predecessor. The old history row stays `completed` with a `post_id` that no longer
exists.

**Fix**
- After `wp_delete_post($predecessor_post_id, true)` succeeds, update the predecessor's history row:
  set `post_id` to `null` and record `predecessor_post_id` in its context, or mark it `superseded`.
  Do NOT restore the pre-PR "null it before generating" ordering — the new ordering is what makes
  feedback retrieval see the predecessor, and that is intentional.
- Decide and document one rule: does the *history* row follow the post, or does it stay as an audit
  record? Whichever is chosen, `AIPS_Generated_Posts_Controller::render_page` and
  `AIPS_Post_Review_Repository::get_generated_posts` must agree, so the unfiltered and
  feedback-filtered listings return the same rows and the same totals.

**Verify:** regenerate a post, then assert the Generated Posts listing returns the same item count
and `total` on both the unfiltered (`get_history`) and feedback-filtered (`get_generated_posts`)
paths.

### 3b — Partial failure on delete

`class-aips-post-review.php:471`: when `wp_delete_post` fails, `AIPS_Ajax_Response::error()` dies
after the replacement was already created — both posts survive and the success history record is
never written. Log the orphan (history `warning` with both post IDs) before responding, and surface
the replacement post ID in the error payload so the admin can clean up.

---

## Slice 4 — Content hash divergence

**Finding:** `includes/class-aips-post-feedback-retrieval-service.php:71` hashes
`wp_strip_all_tags($title . $excerpt . $content)`, while
`AIPS_Post_Feedback_Service::calculate_content_hash()` (`class-aips-post-feedback-service.php:141`)
hashes `$title . $excerpt . wp_strip_all_tags($content)`. Any markup in the title or excerpt makes
the hashes differ, so unedited posts are permanently penalised by `edited_content_weight` (0.35).

**Fix**
- Delete the inline hash in `rank_candidate()` and call
  `AIPS_Post_Feedback_Service::calculate_content_hash($post)` — promote it to `public static` and
  have both sides use it.
- Inject the service (or a small `AIPS_Post_Feedback_Content_Hash` helper) into
  `AIPS_Post_Feedback_Retrieval_Service` rather than duplicating the expression.

**Verify:** test that a post with `<em>` in its excerpt, stored and then immediately re-ranked
without modification, yields `integrity_factor === 1.0`.

---

## Slice 5 — Bulk apply has no loading state

**Finding:** `assets/js/admin-post-feedback.js:180` — `$button.closest('.aips-post-feedback-bulk')`
matches nothing; `templates/admin/tab-generated-posts.php:98` renders `<div class="tablenav top">`.
`setLoading()` is a no-op, so nothing is disabled and a second click fires a concurrent bulk request
that appends duplicate feedback rows.

**Fix**
- Add `aips-post-feedback-bulk` to the wrapper: `<div class="tablenav top aips-post-feedback-bulk">`.
- Additionally guard `bulkApply()` with an in-flight flag so the handler is idempotent even if the
  wrapper class is later renamed.

**Verify:** manual — 2 rapid clicks on Apply produce exactly one `aips_post_feedback_bulk` request.
Extend `Test_AIPS_Generated_Post_Feedback_UI.php` to assert the wrapper class is present in the
rendered markup.

---

## Slice 6 — Unbounded aggregation in the generation hot path

**Finding:** `includes/class-aips-post-feedback-repository.php:123` — the `latest` derived table is
`SELECT post_id, MAX(id) FROM {table} GROUP BY post_id` with no bound; the `LIMIT` only caps the
final rows. This runs once per generated post whenever feedback is enabled.

**Fix**
- Bound the aggregation: add a recency floor (`WHERE created_at >= %d`, e.g. 18 months) and/or
  restrict the derived table to the scope in play (`template_id`, `author_id`, or the global pool)
  before grouping.
- Consider a covering index on `(created_at, post_id, id)` if the recency floor is chosen.
- Optionally cache the candidate set per (author_id, template_id) for the duration of a cron slice.

**Verify:** benchmark via `php bin/benchmark.php` before/after with a seeded feedback table; confirm
no regression against `.github/performance-baseline.json`.

---

## Slice 7 — Assets enqueued while the feature is off

**Finding:** `includes/class-aips-admin-assets.php:446` — `enqueue_global_assets()` calls
`enqueue_post_feedback_assets()` unconditionally, unlike the native-post branch at line 83 which
correctly checks `aips_post_feedback_enabled`.

**Fix**
- Gate the call on `AIPS_Config::get_instance()->get_option('aips_post_feedback_enabled')`, matching
  line 83. Read the option once and reuse it for both branches.
- While here: normalise the mixed tab/space indentation introduced in this file (lines 79–86,
  357, 422, 442–446).

**Verify:** with the master switch off, no `admin-post-feedback.js` / `.css` handle is registered on
any plugin admin page.

---

## Slice 8 — Prompt budget is per-component, not total

**Finding:** `includes/class-aips-post-feedback-prompt-context.php:126` — `serialize()` truncates
each of `content` / `title` / `excerpt` / `metadata` to the full `prompt_budget_chars`, and
`serialize_combined()` applies the same full budget again to `metadata_turn`. A configured 4000
becomes up to ~16000 characters across one generation run.

**Fix (pick one, document it in the settings description):**
- (a) Treat the setting as a per-component ceiling and relabel the field
  ("Prompt budget per component (characters)") plus the tooltip; or
- (b) Treat it as a run-wide budget: split it across the components that actually have guidance,
  e.g. `floor($budget / max(1, count($components_with_content)))`, and render `metadata_turn` from
  the already-truncated sections rather than re-truncating.

Recommendation: (b), since the field currently reads as a single spend ceiling.

**Also in this slice (cosmetic, no behaviour change):**
- `routes()` returns a `'full'` / `'limited'` level that is never read — either honour it (e.g.
  `'limited'` emits instructions but not excerpts) or drop the values and return a plain list.
- `AIPS_Post_Feedback_Retrieval_Service::__construct` builds `AIPS_Post_Embeddings_Repository` but
  never uses `$this->embedding_repository`; remove the dead dependency so an unused service is not
  constructed on every enabled run.
- `AIPS_Post_Feedback_Controller::bulk_feedback` hardcodes "Select between 1 and 100 posts." —
  interpolate `self::MAX_BULK`.

---

## Test / verification pass (after all slices)

```bash
bash scripts/run-wp-tests-docker.sh
```

Focused runs while iterating:

```bash
cd ai-post-scheduler && vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Schema.php tests/Test_AIPS_Post_Feedback_Scope_Persistence.php tests/Test_AIPS_Post_Feedback_Lifecycle.php tests/Test_AIPS_Post_Feedback_Regeneration.php
```

Manual smoke, on an install upgraded from 3.4.2 (not a fresh install — that is the case Slice 1 breaks):

1. Upgrade, confirm `wp_aips_post_feedback` exists and both template/author columns are present.
2. Enable the master switch; Like and Dislike a generated post from both the Generated Posts tab and
   the native editor meta box.
3. Create a NEW template with feedback weight overrides, reopen it, confirm the values persisted.
4. Bulk-apply feedback to several posts; double-click Apply and confirm one request.
5. Regenerate a post from the review queue; confirm the Generated Posts listing shows a full page
   and identical totals with and without the feedback filter.
6. Turn the master switch off; confirm no feedback assets load and the AJAX endpoints reject.
