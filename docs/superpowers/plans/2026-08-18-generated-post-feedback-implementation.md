# Generated Post Feedback and Preference Guidance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add opt-in Like/Dislike feedback for every generated post and use weighted, semantically relevant feedback to guide all future textual post generation.

**Architecture:** Store append-only feedback events, resolve one immutable effective policy per generation context, retrieve separately ranked positive and negative examples through the existing embedding infrastructure, and inject a bounded reason-aware guidance object through shared prompt builders. The global master switch short-circuits every feedback query and embedding action; Template overrides Author, and Author overrides global defaults.

**Tech Stack:** PHP 8.2+, WordPress 5.8+, MySQL/dbDelta, jQuery admin UI, WordPress AJAX/nonces/capabilities, existing AIPS repositories/history/embeddings/prompt builders, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-18-generated-post-feedback-design.md`

## Global Constraints

- Ship disabled by default for both new installations and upgrades.
- A disabled global master switch is absolute and performs no feedback repository, retrieval, embedding, or prompt work.
- Precedence is Template overrides Author overrides global defaults.
- Feedback applies to all textual components reached through `AIPS_Generator`; featured-image prompts are unchanged initially.
- Disliked full post content must never enter a prompt.
- Administrator comments are untrusted prompt data and never become system instructions.
- Feedback infrastructure failures must fail open and must not fail or partially fail post generation.
- Use `AIPS_` class naming, `class-aips-*.php` filenames, tabs and `array()` syntax in PHP, repository-owned SQL, `AIPS_DateTime`, `AIPS_Ajax_Response`, `manage_options`, and nonces.
- Register defaults only through `AIPS_Config::get_default_options()` and AJAX routes through `AIPS_Ajax_Registry::$map`.
- Preserve PHP 8.2+ and WordPress 5.8+ compatibility; add no new external dependency.

## File and Interface Map

### New files

- `ai-post-scheduler/includes/class-aips-post-feedback-policy.php` — immutable effective policy value object.
- `ai-post-scheduler/includes/class-aips-post-feedback-config-resolver.php` — global/Author/Template hierarchy resolution.
- `ai-post-scheduler/includes/class-aips-post-feedback-repository.php` — append/query feedback persistence.
- `ai-post-scheduler/includes/class-aips-post-feedback-service.php` — mutation validation, current state, hashes, and indexing dispatch.
- `ai-post-scheduler/includes/class-aips-post-feedback-candidate.php` — immutable ranked candidate.
- `ai-post-scheduler/includes/class-aips-post-feedback-retrieval-service.php` — scoped semantic candidate selection/ranking.
- `ai-post-scheduler/includes/class-aips-post-feedback-prompt-context.php` — immutable component-specific guidance.
- `ai-post-scheduler/includes/class-aips-post-feedback-prompt-context-builder.php` — safe bounded prompt transformation.
- `ai-post-scheduler/includes/class-aips-post-feedback-controller.php` — AJAX read/mutation/bulk handlers.
- `ai-post-scheduler/includes/class-aips-post-feedback-editor.php` — native editor meta box.
- `ai-post-scheduler/templates/partials/post-feedback-controls.php` — shared control markup.
- `ai-post-scheduler/assets/js/admin-post-feedback.js` — shared interaction logic.
- `ai-post-scheduler/assets/css/admin-post-feedback.css` — selected/loading/dialog styles.
- Focused PHPUnit files named in each task.

### Existing files with primary changes

- `includes/class-aips-db-manager.php`, `includes/class-aips-db-migrations.php` — schema and idempotent upgrade.
- `includes/class-aips-config.php`, `includes/class-aips-settings.php`, `includes/class-aips-settings-ui.php`, `templates/admin/settings.php` — global controls.
- Author/Template repository, DTO, controller, template, and JS files — nullable enable state and sparse overrides.
- `includes/class-aips-ajax-registry.php`, `includes/class-aips-admin-assets.php` — controller routing and assets.
- `includes/class-aips-post-review-repository.php`, `templates/admin/tab-generated-posts.php` — status-inclusive list/filter integration.
- `includes/class-aips-post-embeddings-repository.php`, `includes/class-aips-internal-links-service.php` — rated-post indexing/retrieval reuse.
- `includes/class-aips-generator.php` and specialized post prompt builders — one resolved guidance context per generation.
- Regeneration handlers — predecessor metadata without copying feedback.

---

### Task 1: Add schema and registered defaults

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-db-manager.php`
- Modify: `ai-post-scheduler/includes/class-aips-db-migrations.php`
- Modify: `ai-post-scheduler/includes/class-aips-config.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Schema.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Config.php`

**Interfaces:**
- Produces table `{$wpdb->prefix}aips_post_feedback`.
- Produces nullable `feedback_enabled` and `feedback_config` columns on `aips_authors` and `aips_templates`.
- Produces global option keys consumed by `AIPS_Post_Feedback_Config_Resolver`.

- [ ] **Step 1: Write failing schema/default tests**

Assert fresh schema contains the feedback table and columns, the datetime map includes `aips_post_feedback.created_at`, migrations can run twice, and defaults equal:

```php
$expected = array(
	'aips_post_feedback_enabled'                 => false,
	'aips_post_feedback_like_weight'             => 1.0,
	'aips_post_feedback_dislike_weight'          => 1.25,
	'aips_post_feedback_similarity_weight'       => 1.0,
	'aips_post_feedback_recency_weight'          => 0.35,
	'aips_post_feedback_author_match_weight'     => 1.25,
	'aips_post_feedback_template_match_weight'   => 1.5,
	'aips_post_feedback_global_pool_weight'      => 0.5,
	'aips_post_feedback_max_examples'            => 6,
	'aips_post_feedback_min_similarity'          => 0.70,
	'aips_post_feedback_min_samples'             => 1,
	'aips_post_feedback_prompt_budget_chars'     => 4000,
	'aips_post_feedback_edited_content_weight'   => 0.35,
);
```

- [ ] **Step 2: Run focused tests and confirm failure**

Run from `ai-post-scheduler/`:

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Schema.php tests/Test_AIPS_Config.php
```

Expected: failures for missing table, columns, datetime registration, and option keys.

- [ ] **Step 3: Add the fresh-install schema**

Use an append-only table with `id`, `post_id`, nullable `history_id`, `user_id`, `reaction varchar(16)`, nullable `reason_category`, nullable `comment`, `content_hash char(64)`, nullable `author_id`, nullable `template_id`, and unsigned `created_at`. Add indexes for `(post_id,id)`, `(reaction,id)`, `(author_id,reaction,id)`, `(template_id,reaction,id)`, `history_id`, and `created_at`. Add the two nullable columns to Author and Template definitions and register the table in schema catalogs/datetime maps.

- [ ] **Step 4: Add an idempotent versioned migration**

Add a migration method to create/repair the table and conditionally add Author/Template columns using existing `SHOW COLUMNS` guards. Register it in migration order before the final `dbDelta`/version stamp. Running it twice must not alter existing feedback events.

- [ ] **Step 5: Register exact defaults**

Add the option map above to `AIPS_Config::get_default_options()`. Do not enable the feature in an activation hook or migration.

- [ ] **Step 6: Run focused tests and schema checks**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Schema.php tests/Test_AIPS_Config.php
php -l includes/class-aips-db-manager.php
php -l includes/class-aips-db-migrations.php
php -l includes/class-aips-config.php
```

Expected: tests pass and each lint reports no syntax errors.

- [ ] **Step 7: Commit**

```bash
git add includes/class-aips-db-manager.php includes/class-aips-db-migrations.php includes/class-aips-config.php tests/Test_AIPS_Post_Feedback_Schema.php tests/Test_AIPS_Config.php
git commit -m "feat: add generated post feedback schema"
```

### Task 2: Implement immutable policy and hierarchy resolver

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-policy.php`
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-config-resolver.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Config_Resolver.php`

**Interfaces:**
- Produces `AIPS_Post_Feedback_Policy::is_enabled(): bool`.
- Produces `AIPS_Post_Feedback_Policy::get(string $key)` and `to_array(): array`.
- Produces `AIPS_Post_Feedback_Config_Resolver::resolve(AIPS_Generation_Context $context): AIPS_Post_Feedback_Policy`.
- Consumes global AIPS options plus optional `feedback_enabled` and `feedback_config` values exposed by context subjects.

- [ ] **Step 1: Write failing precedence tests**

Cover global-off short circuit, inherited global enablement, Author disable, Author sparse overrides, Template disable, Template-over-Author, malformed JSON, unknown keys, and numeric clamping. Inject a config reader callable into the resolver so tests do not depend on global option mutation.

```php
$policy = $resolver->resolve($context_with_global_author_and_template);
$this->assertTrue($policy->is_enabled());
$this->assertSame(2.0, $policy->get('like_weight'));
$this->assertSame(0.82, $policy->get('min_similarity'));
```

- [ ] **Step 2: Run the resolver test and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Config_Resolver.php
```

Expected: failure because both classes are absent.

- [ ] **Step 3: Implement the immutable policy**

Normalize to these internal keys: `enabled`, `like_weight`, `dislike_weight`, `similarity_weight`, `recency_weight`, `author_match_weight`, `template_match_weight`, `global_pool_weight`, `max_examples`, `min_similarity`, `min_samples`, `prompt_budget_chars`, `edited_content_weight`, and `reason_weights`. Reject mutation after construction.

- [ ] **Step 4: Implement deterministic resolution**

Return immediately when global master is false. Otherwise merge global values, Author sparse values, then Template sparse values. Apply enabled state at each applicable scope. Allow only registered keys; clamp similarity to `0..1`, counts/budget to positive bounds, and influence weights to `0..10`.

- [ ] **Step 5: Run tests and lint**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Config_Resolver.php
php -l includes/class-aips-post-feedback-policy.php
php -l includes/class-aips-post-feedback-config-resolver.php
```

- [ ] **Step 6: Commit**

```bash
git add includes/class-aips-post-feedback-policy.php includes/class-aips-post-feedback-config-resolver.php tests/Test_AIPS_Post_Feedback_Config_Resolver.php
git commit -m "feat: resolve post feedback policy hierarchy"
```

### Task 3: Expose Author and Template policy persistence

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-authors-repository.php`
- Modify: `ai-post-scheduler/includes/class-aips-template-repository.php`
- Modify: `ai-post-scheduler/includes/class-aips-template-data.php`
- Modify: `ai-post-scheduler/includes/class-aips-template-entry.php`
- Modify: `ai-post-scheduler/includes/class-aips-authors-controller.php`
- Modify: `ai-post-scheduler/includes/class-aips-templates-controller.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Scope_Persistence.php`

**Interfaces:**
- Produces nullable integer `feedback_enabled` and sparse array `feedback_config` on repository results/DTOs.
- Produces controller sanitizer `sanitize_feedback_config($raw): array` or a shared equivalent.
- Consumed by the config resolver from Task 2.

- [ ] **Step 1: Write failing repository/controller tests**

Verify `NULL`, `0`, and `1` round-trip distinctly; sparse JSON preserves only allowed keys; invalid numbers are clamped; unknown keys are dropped; absent submitted fields preserve inheritance.

- [ ] **Step 2: Run the focused test and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Scope_Persistence.php
```

- [ ] **Step 3: Update repository allowlists and DTO mapping**

Add both columns without decoding unrelated `details` fields. Decode `feedback_config` to an array at the boundary used by the resolver while preserving raw DB compatibility in repository writes.

- [ ] **Step 4: Add shared sanitization and controller save handling**

Accept enable values `inherit`, `enabled`, and `disabled`; persist them as `NULL`, `1`, and `0`. Sanitize only the policy keys defined in Task 2 and encode with `wp_json_encode()`.

- [ ] **Step 5: Run tests and lint changed PHP files**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Scope_Persistence.php
php -l includes/class-aips-authors-controller.php
php -l includes/class-aips-templates-controller.php
```

- [ ] **Step 6: Commit**

```bash
git add includes/class-aips-authors-repository.php includes/class-aips-template-repository.php includes/class-aips-template-data.php includes/class-aips-template-entry.php includes/class-aips-authors-controller.php includes/class-aips-templates-controller.php tests/Test_AIPS_Post_Feedback_Scope_Persistence.php
git commit -m "feat: persist author and template feedback overrides"
```

### Task 4: Add global, Author, and Template settings UI

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-settings.php`
- Modify: `ai-post-scheduler/includes/class-aips-settings-ui.php`
- Modify: `ai-post-scheduler/templates/admin/settings.php`
- Modify: `ai-post-scheduler/templates/admin/authors.php`
- Modify: `ai-post-scheduler/templates/admin/templates.php`
- Modify: `ai-post-scheduler/assets/js/authors.js`
- Modify: `ai-post-scheduler/assets/js/templates.js`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Settings_UI.php`

**Interfaces:**
- Consumes option/default keys from Task 1 and scope controller fields from Task 3.
- Produces saved Settings fields and scope forms with inheritance/override semantics.

- [ ] **Step 1: Write failing registration/render/sanitization tests**

Assert every global field is registered, master defaults unchecked, numeric fields render bounds, Author/Template forms render three-state enable selectors, and unchecked master submission saves `0`.

- [ ] **Step 2: Run the UI test and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Settings_UI.php
```

- [ ] **Step 3: Register the Generated Post Feedback settings section**

Add master, weights, thresholds, counts, prompt budget, and edited-content weight. Use a hidden `0` input for the master checkbox and documented min/max/step attributes. Explain that core-off cannot be overridden.

- [ ] **Step 4: Add scope controls**

Render `Inherit global setting`, `Enabled`, and `Disabled`, followed by an “Override weights” disclosure. Populate existing sparse values; leave inherited values visibly labeled with their current global effective value.

- [ ] **Step 5: Add form JavaScript**

Toggle the override panel without erasing saved sparse values. Ensure edit forms populate from server payloads and reset forms restore inheritance.

- [ ] **Step 6: Verify tests, lint, and JS syntax**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Settings_UI.php
php -l includes/class-aips-settings.php
php -l includes/class-aips-settings-ui.php
node --check assets/js/authors.js
node --check assets/js/templates.js
```

- [ ] **Step 7: Commit**

```bash
git add includes/class-aips-settings.php includes/class-aips-settings-ui.php templates/admin/settings.php templates/admin/authors.php templates/admin/templates.php assets/js/authors.js assets/js/templates.js tests/Test_AIPS_Post_Feedback_Settings_UI.php
git commit -m "feat: add post feedback configuration controls"
```

### Task 5: Implement append-only feedback repository

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-repository.php`
- Test: `ai-post-scheduler/tests/AIPS_Post_Feedback_Repository_Test.php`

**Interfaces:**
- Produces `append_event(array $event): int|WP_Error`.
- Produces `get_current_for_post(int $post_id): ?object`.
- Produces `get_current_for_posts(array $post_ids): array<int,object>`.
- Produces `get_history_for_post(int $post_id, int $limit = 100): array`.
- Produces `get_active_candidates(array $scope, int $limit): array`.

- [ ] **Step 1: Write failing repository tests**

Cover append, current state, Like-to-Dislike transition, Clear, complete audit ordering, multi-post current lookup, no N+1 loop, scope queries, and SQL-safe empty lists.

```php
$repository->append_event($liked);
$repository->append_event($cleared);
$this->assertSame('cleared', $repository->get_current_for_post($post_id)->reaction);
$this->assertCount(2, $repository->get_history_for_post($post_id));
```

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/AIPS_Post_Feedback_Repository_Test.php
```

- [ ] **Step 3: Implement prepared persistence and latest-event queries**

Use `MAX(id)` grouped by `post_id` or an equivalent indexed anti-join. Active candidates must join only the latest event and exclude `cleared`. Bound every limit and prepare all external values.

- [ ] **Step 4: Run tests, repository boundary check, and lint**

```bash
vendor/bin/phpunit tests/AIPS_Post_Feedback_Repository_Test.php
php tools/check-repository-boundary.php
php -l includes/class-aips-post-feedback-repository.php
```

- [ ] **Step 5: Commit**

```bash
git add includes/class-aips-post-feedback-repository.php tests/AIPS_Post_Feedback_Repository_Test.php
git commit -m "feat: store append-only generated post feedback"
```

### Task 6: Implement feedback service, hashes, and indexing dispatch

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-service.php`
- Modify: `ai-post-scheduler/includes/class-aips-post-embeddings-repository.php`
- Modify: `ai-post-scheduler/includes/class-aips-internal-links-service.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Service.php`

**Interfaces:**
- Produces `record(int $post_id, string $reaction, ?string $reason, ?string $comment, int $user_id): array|WP_Error`.
- Produces `clear(int $post_id, int $user_id): array|WP_Error`.
- Produces `get_current(int $post_id): ?object` and `get_current_many(array $post_ids): array`.
- Produces `calculate_content_hash(WP_Post $post): string`.
- Consumes repository from Task 5 and existing embedding/indexing services.

- [ ] **Step 1: Write failing service tests**

Cover allowed reactions/reasons, optional fields, generated marker enforcement, deleted post, length bounds, HTML/control stripping, origin snapshot from history, stable normalized hash, changed-content hash, Like/Dislike indexing request, and Clear without indexing.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Service.php
```

- [ ] **Step 3: Implement validation and event assembly**

Use the exact reason allowlist from the spec. Bound comments to 2,000 characters after `sanitize_textarea_field()`. Normalize title, excerpt, and stripped content before `hash('sha256', ...)`. Resolve `history_id`, `author_id`, and `template_id` from the latest matching generation history.

- [ ] **Step 4: Add rated-post indexing reuse**

Expose a method that ensures any generated post status can be indexed for feedback, without changing the published-post defaults used by Internal Links. Store/retrieve the embedding through `AIPS_Post_Embeddings_Repository`; use title, excerpt, topic, and bounded content, excluding feedback comments.

- [ ] **Step 5: Fail indexing open**

If embeddings are unavailable, persist feedback, schedule the existing background mechanism where possible, and return a successful feedback response containing `index_status = pending|unavailable`.

- [ ] **Step 6: Run tests and lint**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Service.php
php -l includes/class-aips-post-feedback-service.php
php -l includes/class-aips-post-embeddings-repository.php
php -l includes/class-aips-internal-links-service.php
```

- [ ] **Step 7: Commit**

```bash
git add includes/class-aips-post-feedback-service.php includes/class-aips-post-embeddings-repository.php includes/class-aips-internal-links-service.php tests/Test_AIPS_Post_Feedback_Service.php
git commit -m "feat: validate feedback and index rated posts"
```

### Task 7: Add secure AJAX endpoints and route registration

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-controller.php`
- Modify: `ai-post-scheduler/includes/class-aips-ajax-registry.php`
- Modify: `ai-post-scheduler/ai-post-scheduler.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Controller.php`

**Interfaces:**
- Produces actions `aips_post_feedback_set`, `aips_post_feedback_clear`, `aips_post_feedback_get`, and `aips_post_feedback_bulk`.
- Consumes `AIPS_Post_Feedback_Service` from Task 6.

- [ ] **Step 1: Write failing controller tests**

Assert hook registration, `manage_options`, nonce `aips_post_feedback`, post/reaction/reason sanitization, generated marker enforcement via service, single and bulk success shapes, partial bulk failures, and maximum bulk size of 100.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Controller.php
```

- [ ] **Step 3: Implement controller handlers**

Return current feedback with `post_id`, `reaction`, `reason_category`, `comment`, `updated_by`, and display timestamp. For bulk calls return `succeeded`, `failed`, and per-post error messages without aborting successful rows.

- [ ] **Step 4: Register routes and boot only in needed contexts**

Add all four actions to `AIPS_Ajax_Registry::$map`. Register/instantiate the controller in AJAX/admin request contexts, not unrelated frontend requests.

- [ ] **Step 5: Run tests and lint**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Controller.php
php -l includes/class-aips-post-feedback-controller.php
php -l includes/class-aips-ajax-registry.php
```

- [ ] **Step 6: Commit**

```bash
git add includes/class-aips-post-feedback-controller.php includes/class-aips-ajax-registry.php ai-post-scheduler.php tests/Test_AIPS_Post_Feedback_Controller.php
git commit -m "feat: add generated post feedback endpoints"
```

### Task 8: Add Generated Posts controls, filters, and bulk actions

**Files:**
- Create: `ai-post-scheduler/templates/partials/post-feedback-controls.php`
- Create: `ai-post-scheduler/assets/js/admin-post-feedback.js`
- Create: `ai-post-scheduler/assets/css/admin-post-feedback.css`
- Modify: `ai-post-scheduler/includes/class-aips-post-review-repository.php`
- Modify: `ai-post-scheduler/includes/class-aips-generated-posts-controller.php`
- Modify: `ai-post-scheduler/templates/admin/tab-generated-posts.php`
- Modify: `ai-post-scheduler/includes/class-aips-admin-assets.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Generated_Post_Feedback_UI.php`

**Interfaces:**
- Consumes current-state batch lookup and AJAX actions.
- Produces all-status generated-post filtering by `liked`, `disliked`, and `unrated`.

- [ ] **Step 1: Write failing query/render tests**

Assert generated posts can be listed across draft, publish, pending, private, and trash states when requested; current feedback is loaded in one batch; reaction filters return correct rows; buttons render `aria-pressed`; and bulk controls use localized reason labels.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Generated_Post_Feedback_UI.php
```

- [ ] **Step 3: Extend repository/controller list data**

Join or batch-resolve latest feedback without per-row queries. Preserve the existing Pending Review draft-only tab behavior while making the Generated Posts tab explicitly capable of showing all generated statuses.

- [ ] **Step 4: Implement shared controls and dialog**

Render Like/Dislike buttons, optional reason select, optional comment, Save, Cancel, and Clear. Use the shared normalized taxonomy with positive/negative labels and no “Approve/Reject” wording.

- [ ] **Step 5: Implement single and bulk JavaScript**

Use delegated events, nonce-bearing AJAX, loading/disabled states, optimistic UI only after server success, keyboard/Escape behavior, toast notices, and partial-bulk reporting. Changing Like to Dislike appends a new event; Clear calls the clear endpoint.

- [ ] **Step 6: Enqueue/localize assets**

Localize action names, nonce, labels, error messages, maximum bulk size, taxonomy labels, and current filters through `AIPS_Admin_Assets`.

- [ ] **Step 7: Run tests, lint, and JS syntax check**

```bash
vendor/bin/phpunit tests/Test_AIPS_Generated_Post_Feedback_UI.php
php -l includes/class-aips-generated-posts-controller.php
php -l templates/admin/tab-generated-posts.php
node --check assets/js/admin-post-feedback.js
```

- [ ] **Step 8: Commit**

```bash
git add templates/partials/post-feedback-controls.php assets/js/admin-post-feedback.js assets/css/admin-post-feedback.css includes/class-aips-post-review-repository.php includes/class-aips-generated-posts-controller.php templates/admin/tab-generated-posts.php includes/class-aips-admin-assets.php tests/Test_AIPS_Generated_Post_Feedback_UI.php
git commit -m "feat: add feedback controls to generated posts"
```

### Task 9: Add feedback to the native post editor

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-editor.php`
- Modify: `ai-post-scheduler/includes/class-aips-admin-assets.php`
- Modify: `ai-post-scheduler/ai-post-scheduler.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Editor.php`

**Interfaces:**
- Reuses `templates/partials/post-feedback-controls.php` and `admin-post-feedback.js` from Task 8.
- Consumes current feedback from Task 6.

- [ ] **Step 1: Write failing meta-box tests**

Assert the meta box appears only for posts with `AIPS_Post_Manager::META_GENERATED_POST = '1'`, remains visible for published/edited posts, renders current state, and does not mutate feedback during normal `save_post`.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Editor.php
```

- [ ] **Step 3: Implement editor registration/rendering**

Register on supported post edit screens, load current feedback once, and render the shared partial. Do not add a `save_post` mutation handler; feedback writes only through its explicit AJAX controls.

- [ ] **Step 4: Enqueue shared assets conditionally**

Load feedback CSS/JS and localized configuration only when editing a generated post.

- [ ] **Step 5: Run tests and lint**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Editor.php
php -l includes/class-aips-post-feedback-editor.php
```

- [ ] **Step 6: Commit**

```bash
git add includes/class-aips-post-feedback-editor.php includes/class-aips-admin-assets.php ai-post-scheduler.php tests/Test_AIPS_Post_Feedback_Editor.php
git commit -m "feat: add post editor feedback panel"
```

### Task 10: Implement semantic retrieval and deterministic ranking

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-candidate.php`
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-retrieval-service.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Retrieval_Service.php`

**Interfaces:**
- Produces `retrieve(AIPS_Generation_Context $context, AIPS_Post_Feedback_Policy $policy): array{positive: array, negative: array, diagnostics: array}`.
- Candidate exposes event/post IDs, reaction, reason, comment, similarity, final score, integrity state, and safe bounded source fields.
- Consumes feedback repository, post embeddings repository, embeddings service, and feedback service hash method.

- [ ] **Step 1: Write failing ranking tests**

Cover positive/negative separation, minimum samples, threshold exclusion, Template match over Author match over global pool, no double scope multiplication, reaction weights, reason weights, recency decay, matching/edited content hashes, weight zero, deleted/trashed content exclusion, deterministic tie-break by event ID, maximum examples, and missing query/candidate embeddings.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Retrieval_Service.php
```

- [ ] **Step 3: Implement bounded query text and candidate loading**

Build query text from context topic, Template/Author instructions, and a bounded prompt excerpt. Request only a bounded candidate superset, such as `max_examples * 5`, from applicable scopes.

- [ ] **Step 4: Implement ranking formula**

Use:

```php
$score = pow($similarity, max(0.0, $policy->get('similarity_weight')))
	* $scope_multiplier
	* $reaction_multiplier
	* $reason_multiplier
	* $recency_multiplier
	* $integrity_multiplier;
```

Define recency as a bounded half-life-style multiplier controlled by `recency_weight`, never below `0.1`. Use Template boost when Template matches; otherwise Author boost when Author matches; otherwise global-pool weight. Sort by score descending then event ID descending.

- [ ] **Step 5: Implement fail-open diagnostics**

Return empty pools and diagnostic codes `disabled`, `insufficient_samples`, `query_embedding_missing`, `candidate_embedding_missing`, or `repository_error`; do not return `WP_Error` to the generator.

- [ ] **Step 6: Run tests and lint**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Retrieval_Service.php
php -l includes/class-aips-post-feedback-candidate.php
php -l includes/class-aips-post-feedback-retrieval-service.php
```

- [ ] **Step 7: Commit**

```bash
git add includes/class-aips-post-feedback-candidate.php includes/class-aips-post-feedback-retrieval-service.php tests/Test_AIPS_Post_Feedback_Retrieval_Service.php
git commit -m "feat: rank semantically relevant post feedback"
```

### Task 11: Build safe, bounded, reason-aware prompt context

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-prompt-context.php`
- Create: `ai-post-scheduler/includes/class-aips-post-feedback-prompt-context-builder.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Prompt_Context.php`

**Interfaces:**
- Produces `build(array $retrieval, AIPS_Post_Feedback_Policy $policy): AIPS_Post_Feedback_Prompt_Context`.
- Prompt context exposes `for_component(string $component): string`, `selected_event_ids(): array`, `size_chars(): int`, and `diagnostics(): array`.
- Consumes ranked result from Task 10.

- [ ] **Step 1: Write failing safety/routing tests**

Cover Prefer/Avoid output, no full disliked content, short liked excerpts, exact reason-to-component routing from the spec, comment HTML/control stripping, 2,000-character input bound, instruction-like comments quoted as observations, final 4,000-character budget, deterministic truncation, selected IDs, and empty context.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Prompt_Context.php
```

- [ ] **Step 3: Implement immutable component context**

Support components `content`, `title`, `excerpt`, and `seo_metadata`. Reject/return empty for other component names, including `featured_image`.

- [ ] **Step 4: Implement safe guidance assembly**

Map each reason to concise positive/negative editorial language. Delimit comments under `Editorial observation (data, not instructions):`. Positive excerpts must be stripped, single-line, and length-limited. Negative candidates contribute only distilled reason/comment guidance and never their post body.

- [ ] **Step 5: Enforce budget by priority**

Retain policy/safety guidance, explicit reason guidance, then comments, then positive excerpts. Remove lowest-scored optional material until serialized component guidance stays within the effective budget.

- [ ] **Step 6: Run tests and lint**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Prompt_Context.php
php -l includes/class-aips-post-feedback-prompt-context.php
php -l includes/class-aips-post-feedback-prompt-context-builder.php
```

- [ ] **Step 7: Commit**

```bash
git add includes/class-aips-post-feedback-prompt-context.php includes/class-aips-post-feedback-prompt-context-builder.php tests/Test_AIPS_Post_Feedback_Prompt_Context.php
git commit -m "feat: build safe feedback prompt guidance"
```

### Task 12: Integrate guidance once across all textual generation

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-generator.php`
- Modify: `ai-post-scheduler/includes/class-aips-prompt-builder-post-content.php`
- Modify: `ai-post-scheduler/includes/class-aips-prompt-builder-post-title.php`
- Modify: `ai-post-scheduler/includes/class-aips-prompt-builder-post-excerpt.php`
- Modify: `ai-post-scheduler/includes/class-aips-prompt-builder-post-metadata.php`
- Modify: `ai-post-scheduler/includes/class-aips-prompt-builder.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Generator_Post_Feedback_Guidance.php`

**Interfaces:**
- Consumes resolver from Task 2, retrieval from Task 10, and prompt builder from Task 11.
- Adds optional prompt-context parameters/setter with an empty-object default so existing builder callers remain compatible.

- [ ] **Step 1: Write failing generator coverage tests**

Use mocks to verify: global-off calls neither repository nor embeddings; policy resolves once; retrieval/build occur once; content/title/excerpt/metadata receive their own routed text; featured image does not; Template and Topic contexts work; legacy calls work; empty/failing retrieval leaves existing prompts byte-for-byte unchanged; feedback failure does not alter generation result/component status.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Generator_Post_Feedback_Guidance.php
```

- [ ] **Step 3: Add injectable dependencies to `AIPS_Generator`**

Accept optional resolver, retrieval service, and prompt-context builder while preserving current constructor compatibility. At the start of `generate_post_from_context()`, resolve policy and immediately retain an empty context when disabled.

- [ ] **Step 4: Resolve guidance once**

Wrap retrieval/build in `try/catch (Throwable $error)`, log a concise warning/history fallback, and continue with the empty prompt context. Do not call feedback services again for title, excerpt, or metadata.

- [ ] **Step 5: Pass component guidance through specialized builders**

Append a clearly labeled generated-post preference section after mandatory site/Template/Author instructions. Preserve existing public signatures using an optional final parameter or builder-held context. Ensure conversational and non-conversational metadata paths both receive routed guidance.

- [ ] **Step 6: Record observability**

Write enabled/effective scope, candidate counts, selected event IDs, component sizes, and fallback code to history without full comments or source posts.

- [ ] **Step 7: Run focused and nearby prompt tests**

```bash
vendor/bin/phpunit tests/Test_AIPS_Generator_Post_Feedback_Guidance.php tests/Test_Prompt_Builder_Post_Content.php tests/Test_Prompt_Builder_Post_Title.php tests/Test_Prompt_Builder_Post_Excerpt.php
php -l includes/class-aips-generator.php
```

- [ ] **Step 8: Commit**

```bash
git add includes/class-aips-generator.php includes/class-aips-prompt-builder.php includes/class-aips-prompt-builder-post-content.php includes/class-aips-prompt-builder-post-title.php includes/class-aips-prompt-builder-post-excerpt.php includes/class-aips-prompt-builder-post-metadata.php tests/Test_AIPS_Generator_Post_Feedback_Guidance.php
git commit -m "feat: guide all post generation with feedback"
```

### Task 13: Preserve regeneration lineage without copying reactions

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-post-review.php`
- Modify: `ai-post-scheduler/includes/class-aips-author-post-generator.php`
- Modify: `ai-post-scheduler/includes/class-aips-bulk-generator-service.php`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Regeneration.php`

**Interfaces:**
- Produces `_aips_predecessor_post_id` on a newly regenerated post.
- Produces `predecessor_post_id` in new generation-history metadata/log detail.
- Does not append or copy feedback to the new post.

- [ ] **Step 1: Write failing single/bulk/Author Topic regeneration tests**

Create an old liked/disliked post, regenerate it, and assert the new post has predecessor metadata, old audit remains, new current feedback is null, and old feedback is eligible to guide regeneration before old content is removed.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Regeneration.php
```

- [ ] **Step 3: Thread old/new IDs through regeneration results**

Persist `_aips_predecessor_post_id` and history metadata before permanently deleting or trashing the predecessor. Do not clone `_aips_generated_post_feedback` state or append a feedback event for the new post.

- [ ] **Step 4: Run tests and lint changed handlers**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Regeneration.php
php -l includes/class-aips-post-review.php
php -l includes/class-aips-author-post-generator.php
```

- [ ] **Step 5: Commit**

```bash
git add includes/class-aips-post-review.php includes/class-aips-author-post-generator.php includes/class-aips-bulk-generator-service.php tests/Test_AIPS_Post_Feedback_Regeneration.php
git commit -m "feat: preserve feedback regeneration lineage"
```

### Task 14: Add lifecycle controls, documentation, and end-to-end verification

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-data-management-repository.php`
- Modify: `ai-post-scheduler/includes/class-aips-data-management-export-json.php`
- Modify: `ai-post-scheduler/includes/class-aips-data-management-import-json.php`
- Modify: `ai-post-scheduler/includes/class-aips-data-management-export-mysql.php`
- Modify: `ai-post-scheduler/includes/class-aips-data-management-import-mysql.php`
- Modify: `ai-post-scheduler/includes/class-aips-db-manager.php`
- Modify: `docs/FEATURE_LIST.md`
- Modify: `docs/AI_AGENT_REFERENCE.md`
- Modify: `docs/HOOKS.md` if new hooks/filters are added
- Modify: `CHANGELOG.md`
- Test: `ai-post-scheduler/tests/Test_AIPS_Post_Feedback_Lifecycle.php`

**Interfaces:**
- Produces explicit export/delete/retention behavior for post feedback.
- Documents settings, hierarchy, taxonomy, security, failure behavior, and opt-in rollout.

- [ ] **Step 1: Write failing lifecycle tests**

Verify DB repair/reinstall includes the new table/columns, configured plugin data deletion removes feedback, preservation mode retains it, and deleting/trashing a post never silently rewrites audit events.

- [ ] **Step 2: Run and confirm failure**

```bash
vendor/bin/phpunit tests/Test_AIPS_Post_Feedback_Lifecycle.php
```

- [ ] **Step 3: Implement lifecycle behavior**

Add the table to the existing centralized deletion/export catalogs. Clear-all feedback must require `manage_options`, a dedicated nonce, an explicit confirmation, and repository-owned deletion. Report removed row counts.

- [ ] **Step 4: Update documentation**

Document the master switch, inheritance precedence, weights/defaults, Like/Dislike semantics, optional reasons/comments, prompt use, embedding behavior, regeneration lineage, privacy, and fail-open behavior. Add new hooks/filters only when implementation actually exposes them, with exact signatures.

- [ ] **Step 5: Run the focused feature suite**

```bash
vendor/bin/phpunit \
  tests/Test_AIPS_Post_Feedback_Schema.php \
  tests/Test_AIPS_Post_Feedback_Config_Resolver.php \
  tests/Test_AIPS_Post_Feedback_Scope_Persistence.php \
  tests/Test_AIPS_Post_Feedback_Settings_UI.php \
  tests/AIPS_Post_Feedback_Repository_Test.php \
  tests/Test_AIPS_Post_Feedback_Service.php \
  tests/Test_AIPS_Post_Feedback_Controller.php \
  tests/Test_AIPS_Generated_Post_Feedback_UI.php \
  tests/Test_AIPS_Post_Feedback_Editor.php \
  tests/Test_AIPS_Post_Feedback_Retrieval_Service.php \
  tests/Test_AIPS_Post_Feedback_Prompt_Context.php \
  tests/Test_AIPS_Generator_Post_Feedback_Guidance.php \
  tests/Test_AIPS_Post_Feedback_Regeneration.php \
  tests/Test_AIPS_Post_Feedback_Lifecycle.php
```

Expected: all focused feature tests pass.

- [ ] **Step 6: Run static verification**

```bash
find includes templates -type f -name '*.php' -print0 | xargs -0 -n1 php -l
node --check assets/js/admin-post-feedback.js
php tools/check-repository-boundary.php
git diff --check
```

Expected: no PHP syntax errors, valid JS syntax, repository boundary pass, and no whitespace errors.

- [ ] **Step 7: Manually verify the acceptance flow**

On a WordPress development site: confirm master-off causes no feedback retrieval logs; enable globally; set an Author override; set a Template override; Like and Dislike posts from both admin surfaces; change and clear reactions; filter and bulk-rate Generated Posts; generate from Template and Author Topic paths; inspect history for selected event IDs without comments/full content; edit a rated post and confirm down-weighting; regenerate and confirm predecessor/new-unrated behavior; disable globally and confirm all scope overrides become ineffective.

- [ ] **Step 8: Commit**

```bash
git add docs/FEATURE_LIST.md docs/AI_AGENT_REFERENCE.md docs/HOOKS.md CHANGELOG.md includes tests
git commit -m "docs: complete generated post feedback rollout"
```

## Completion Gate

- [ ] Every acceptance criterion in `docs/superpowers/specs/2026-08-18-generated-post-feedback-design.md` maps to a passing focused test or the explicit manual verification flow.
- [ ] The global-off test proves zero feedback repository and embedding calls.
- [ ] No prompt contains full disliked content or raw instruction-capable comments.
- [ ] All generation-context coverage tests pass without changing existing prompt output when feedback is disabled or empty.
- [ ] Fresh-install and upgraded schemas match.
- [ ] `git diff --check`, PHP lint, JavaScript syntax checks, and repository boundary checks pass.
- [ ] Final review uses `superpowers:verification-before-completion` before any completion claim.
