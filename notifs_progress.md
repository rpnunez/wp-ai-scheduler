# Notification System Implementation Progress

Branch: `feature/notif-fury`
PR: https://github.com/rpnunez/wp-ai-scheduler/pull/1022

---

## Priority Tiers

| Tier | Types | Status |
|------|-------|--------|
| **Highest** (DB + Email immediate) | generation_failed, quota_alert, integration_error, scheduler_error, system_error | ✅ Complete |
| **High** (DB immediate; Email digest default) | template_generated, manual_generation_completed, post_ready_for_review, post_rejected, partial_generation_completed | 🔄 In progress |
| **Medium/Low** (Digest or DB-only) | daily digest, weekly summary, monthly report, history_cleanup, seeder_complete, template_change, author_suggestions | ⬜ Not started |

---

## Relevant Files

| File | Role |
|------|------|
| `ai-post-scheduler/includes/class-aips-notifications.php` | Central service — type registry, convenience methods, hook bindings, dispatch |
| `ai-post-scheduler/includes/class-aips-notification-templates.php` | Email template registry and rendering |
| `ai-post-scheduler/includes/class-aips-notifications-repository.php` | DB persistence, dedupe lookup, read/unread |
| `ai-post-scheduler/includes/class-aips-config.php` | Default values for `aips_notification_preferences` |
| `ai-post-scheduler/includes/class-aips-settings.php` | Settings UI — registers and renders per-type channel-mode selectors |
| `ai-post-scheduler/includes/class-aips-schedule-processor.php` | Emitter: fires `aips_schedule_execution_completed` on cron success → wire `template_generated` here |
| `ai-post-scheduler/includes/class-aips-generator.php` | Emitter: fires `aips_post_generated` on success → wire `manual_generation_completed` here |
| `ai-post-scheduler/includes/class-aips-author-post-generator.php` | Emitter: manual topic generation success → wire `manual_generation_completed` here |
| `ai-post-scheduler/includes/class-aips-post-review.php` | Emitter: fires `aips_post_review_published`, `aips_post_review_deleted` → wire `post_ready_for_review` + `post_rejected` |
| `ai-post-scheduler/includes/class-aips-partial-generation-notifications.php` | Existing partial generation notification class (check for overlap with new `partial_generation_completed`) |
| `ai-post-scheduler/includes/class-aips-admin-bar.php` | Admin toolbar display (already handles title/level columns) |
| `ai-post-scheduler/assets/css/admin-bar.css` | Severity CSS (warning/error level classes already added in Phase 5) |

---

## Tier: Highest Priority — ✅ COMPLETE

All five highest-priority types are fully wired end-to-end.

### What was done
1. **Registry** — all 5 types added to `get_notification_type_registry()` with label, description, `default_mode = 'both'`, level, and dedupe_window.
2. **Convenience methods** — `generation_failed()`, `quota_alert()`, `integration_error()`, `scheduler_error()`, `system_error()` added to `AIPS_Notifications`.
3. **Hook bindings** — `get_hook_bindings()` registers handlers for `aips_generation_failed`, `aips_quota_alert`, `aips_integration_error`, `aips_scheduler_error`, `aips_system_error`.
4. **Email templates** — all 5 standard alert templates added via `build_standard_alert_template()` in `AIPS_Notification_Templates`.
5. **Settings UI** — `aips_notifications_section` renders per-type channel-mode dropdowns for the 5 types; `aips_notification_preferences` option registered with sanitizer.
6. **Config defaults** — `AIPS_Config` seeds `aips_notification_preferences` with `'both'` for all 5 types.
7. **Emitters wired**:
   - `generation_failed` → `AIPS_Generator::emit_generation_failure_notification()` for manual failures; `AIPS_Author_Post_Generator` for author-topic manual failures.
   - `scheduler_error` → `AIPS_Schedule_Processor` on lock failure and execution failure.
   - `quota_alert` + `integration_error` → `AIPS_AI_Service::emit_quota_alert_notification()` / `emit_integration_error_notification()` with transient-based dedupe.
   - `system_error` → activation failure in `ai-post-scheduler.php` and DB upgrade failure in `AIPS_Upgrades`.
8. **Admin bar** — level-based CSS classes (`aips-notif-level-warning`, `aips-notif-level-error`) applied in PHP and styled in `admin-bar.css`.

---

## Tier: High Priority — 🔄 IN PROGRESS

**Default channel:** DB immediate + Email digest (stored as `'db'` default; user can opt into `'both'` or `'email'`).

### Types to implement

#### 1. `template_generated`
- **Trigger:** `aips_schedule_execution_completed` action (fired in `AIPS_Schedule_Processor::handle_execution_success()` at line ~528)
- **Data available:** `$schedule->schedule_id`, `$schedule->template_id`, `$schedule->name`, `$schedule->frequency`, `$post_ids[]`, first post object
- **Emitter file:** `class-aips-schedule-processor.php` — hook into existing `do_action('aips_schedule_execution_completed', $schedule->schedule_id, $result)`
- **Dedup:** per schedule_id + run, ~60s window to avoid duplicates on multi-post batch
- **Status:** ⬜ Not done

#### 2. `manual_generation_completed`
- **Trigger:** `aips_post_generated` action (fired in `AIPS_Generator::generate_post_from_context()` line ~791–793)
- **Data available:** `$post_id`, `$context` (AIPS_Generation_Context with `get_creation_method()`, `get_type()`, `get_id()`, `get_topic()`), `$history_id`
- **Emitter file:** `class-aips-generator.php` — bind to existing `aips_post_generated` hook, but only when `creation_method === 'manual'`
- **Note:** Scheduled generation (creation_method = 'scheduled') should produce `template_generated`, not this. Guard on creation_method.
- **Status:** ⬜ Not done

#### 3. `post_ready_for_review`
- **Trigger:** When a generated post is saved in draft/pending status (currently `aips_post_generated` hook when `post_status = 'draft'`)
- **Data available:** `$post_id`, post title, post edit/review URL
- **Emitter file:** `class-aips-generator.php` or `class-aips-schedule-processor.php` — emit when generated post has `post_status = 'draft'`
- **Note:** This replaces the existing daily `posts_awaiting_review` digest for immediate per-post review requests. Check whether `AIPS_Post_Review` already fires relevant hooks.
- **Status:** ⬜ Not done

#### 4. `post_rejected`
- **Trigger:** `aips_post_review_deleted` action (fired by `AIPS_Post_Review::ajax_delete_draft_post()`)
- **Data available:** `$post_id`
- **Emitter file:** `class-aips-post-review.php` — hook into existing `do_action('aips_post_review_deleted', $post_id)`
- **Status:** ⬜ Not done

#### 5. `partial_generation_completed`
- **Current state:** `aips_post_generation_incomplete` already fires; `AIPS_Notifications::handle_partial_generation()` and the existing `partial_generation` type handle it.
- **Plan:** Rename/alias `partial_generation` → `partial_generation_completed` in the registry, OR add `partial_generation_completed` as the "High" tier type and keep `partial_generation` as the pre-existing email-only type.
- **Recommendation:** Add `partial_generation_completed` to the registry as a DB-only immediate type (distinct from the existing email-only `partial_generation`). Wire to the same `aips_post_generation_incomplete` hook with higher priority or different handler.
- **Status:** ⬜ Not done

### What needs to be done for High tier

- [ ] **Registry** — add 5 new types to `get_notification_type_registry()` with `default_mode = 'db'` (DB immediate; user can opt into email)
- [ ] **Convenience methods** — add `template_generated()`, `manual_generation_completed()`, `post_ready_for_review()`, `post_rejected()`, `partial_generation_completed()` to `AIPS_Notifications`
- [ ] **Hook bindings** — add bindings for `aips_schedule_execution_completed`, `aips_post_generated` (manual-only guard), `aips_post_review_deleted`, `aips_post_generation_incomplete` (for partial_generation_completed)
- [ ] **Email templates** — add info/success-style templates for all 5 types in `AIPS_Notification_Templates`
- [ ] **Settings UI** — extend settings render loop to include the 5 new High types (may require changing `get_high_priority_notification_types()` or adding a new getter)
- [ ] **Config defaults** — add 5 new defaults to `aips_notification_preferences` in `AIPS_Config` with `'db'`
- [ ] **Sanitizer** — update `sanitize_notification_preferences()` in `AIPS_Settings` to include new types
- [ ] **Emitters**:
  - `template_generated` ← bind to `aips_schedule_execution_completed` in notifications service
  - `manual_generation_completed` ← bind to `aips_post_generated`, filter for `creation_method = 'manual'`
  - `post_ready_for_review` ← bind to `aips_post_generated` when post status = 'draft'
  - `post_rejected` ← bind to `aips_post_review_deleted`
  - `partial_generation_completed` ← bind to `aips_post_generation_incomplete`

---

## Tier: Medium/Low — ⬜ NOT STARTED

Types: `daily_digest`, `weekly_summary`, `monthly_report`, `history_cleanup`, `seeder_complete`, `template_change`, `author_suggestions`

All require new cron hooks or action hook wiring at admin-level classes. Defer until High tier is complete.

---

## Notes & Decisions

- **No 'digest' channel mode exists** in the current implementation — only `off`, `db`, `email`, `both`. "Email digest default" meaning for High tier = set `default_mode = 'db'` by default, let user switch to `'both'` if they want email too.
- **Dedupe:** High-priority success notifications don't need aggressive dedupe (each post/generation is unique). Use a short window (60s) keyed on post_id to prevent double-fire.
- **Scheduled vs manual distinction:** `$context->get_creation_method()` returns `'scheduled'` or `'manual'`. Use this to route to the right notification type at the `aips_post_generated` hook.
- **Sanitizer scope:** The current `sanitize_notification_preferences()` only iterates over `get_high_priority_notification_types()`. Must update to cover all notification types or use a broader registry scan.
- **Settings section:** Currently only renders the 5 high-priority types. Need to either add a new section for High-tier types or expand the existing loop to include all registered types.
