# DTO Adoption Inventory — Phase E

> **Phase E.1 deliverable.** This document maps every place in the codebase where the
> three typed DTOs (`AIPS_Generation_Result`, `AIPS_Schedule_Entry`, `AIPS_Template_Data`)
> and the AJAX response helper (`AIPS_Ajax_Response`) are already adopted, and every place
> that still uses ad-hoc arrays or raw `stdClass` rows that should be migrated.
>
> Flows covered (in priority order): **Generation → Scheduler → Controllers → Responses.**

---

## 1. Current State Summary

| DTO / Helper | Defined Since | Adopted in Production Code? | Tests Green? |
|---|---|---|---|
| `AIPS_Generation_Result` | 2.4.0 | ❌ Not yet | ✅ 9/9 tests pass |
| `AIPS_Schedule_Entry` | 2.4.0 | ❌ Not yet | ✅ 10/10 tests pass (75 % line coverage — `is_due()` without explicit time not exercised) |
| `AIPS_Template_Data` | 2.4.0 | ❌ Not yet | ✅ 9/9 tests pass |
| `AIPS_Ajax_Response` | 2.4.0 | ✅ **Fully adopted** | n/a — all 23 AJAX controllers use it |

The DTOs exist with full `from_row()` factories, typed `readonly` properties, and helper
methods, but none of the production callers have been updated to consume them yet.  All
three DTOs have comprehensive unit tests that pass cleanly.

`AIPS_Ajax_Response` is already the universal AJAX response helper across all controllers.
**Phase E.4 is complete** and requires no additional work.

---

## 2. `AIPS_Generation_Result`

### 2a. What the DTO represents

An immutable value object with three named constructors:

- `::success($post_id, $component_statuses, $generation_time)` — `STATUS_COMPLETED`
- `::partial($post_id, $errors, $component_statuses, $generation_time)` — `STATUS_PARTIAL`
- `::failure($errors, $generation_time)` — `STATUS_FAILED`
- `::from_wp_error(WP_Error)` — convenience wrapper

### 2b. Generator: the producer (Phase E.2 work)

**File:** `includes/class-aips-generator.php`
**Method:** `generate_post()` (public) and `generate_post_from_context()` (private)

Current return type: `int|WP_Error` (the integer is the `$post_id`, the `WP_Error` signals failure).

The private `generate_post_from_context()` already assembles all the data that
`AIPS_Generation_Result` needs:

| Field | Where it exists today |
|---|---|
| `$post_id` | `$this->post_manager->create_post(...)` return value |
| `$component_statuses` | local `$component_statuses` array built throughout the method |
| `$generation_time` | `microtime(true) - $generation_start` |
| `$errors` | `WP_Error` messages when components fail |

**Migration goal:** change `generate_post_from_context()` return to
`AIPS_Generation_Result` and update the public wrapper.  Add a backward-compat `toArray()` if
any caller is hard to update immediately.

### 2c. Callers: the consumers (Phase E.2 work)

All callers currently use `is_wp_error($result)` to branch, then treat the non-error value
as a bare `int` post ID.  Each caller below must be updated to check `$result->is_failure()`
(or `$result->is_success()` / `$result->has_post()`) and read `$result->post_id`.

| File | Line(s) | How it consumes the result today |
|---|---|---|
| `class-aips-schedule-processor.php` | ~432 | `if (is_wp_error($result))` → collects error; else appends `$result` (int) to `$successful_post_ids` |
| `class-aips-schedule-controller.php` | ~197 | `if ($result instanceof WP_Error)` → collects error message; else `$post_ids[] = $result` (int) |
| `class-aips-author-post-generator.php` | ~208 | `if (is_wp_error($post_id))` check; also uses bare int `$post_id` to write `_aips_post_generation_total_time` meta |
| `class-aips-research-controller.php` | ~535 | `$post_id = $generator->generate_post(...)` — used directly as post id |
| `class-aips-onboarding-wizard.php` | ~441 | `$post_id = $generator->generate_post(...)` — used directly as post id |
| `class-aips-history.php` | ~349, ~636 | `if (is_wp_error($result))` → error response; else `'post_id' => $result` |
| `class-aips-post-review.php` | ~443 | `$result = $generator->generate_post($template)` — not shown being is_wp_error checked (line ~443) |
| `class-aips-planner.php` | ~209 | `return $generator->generate_post(...)` — return value passed back to caller |

**Priority order for E.2:** `schedule-processor` → `schedule-controller` → `author-post-generator` →
`history` → `post-review` → `research-controller` → `onboarding-wizard` → `planner`.

---

## 3. `AIPS_Schedule_Entry`

### 3a. What the DTO represents

An immutable value object wrapping one `aips_schedule` DB row, with full type coercions and helpers:
- `is_due(?string $current_time)` — whether `next_run ≤ $current_time`
- `is_circuit_open()` — whether `circuit_state === 'open'`

It also carries the JOIN-populated `$template_name` field for queries that include a templates JOIN.

### 3b. Repository: the producer (Phase E.3 work)

**File:** `includes/class-aips-schedule-repository.php`

All read methods currently return raw `stdClass` rows from `$wpdb->get_row()` /
`$wpdb->get_results()`.  None wrap via `AIPS_Schedule_Entry::from_row()`.

| Method | Returns today | Proposed return type |
|---|---|---|
| `get_all($active_only)` | `array` of `stdClass` | `AIPS_Schedule_Entry[]` |
| `get_by_id($id)` | `stdClass\|null` | `AIPS_Schedule_Entry\|null` |
| `get_due_schedules($time, $limit)` | `array` of `stdClass` (merged template+schedule columns) | `AIPS_Schedule_Entry[]` ¹ |
| `get_upcoming($limit)` | `array` of `stdClass` | `AIPS_Schedule_Entry[]` |
| `get_by_template($template_id)` | `array` of `stdClass` | `AIPS_Schedule_Entry[]` |
| `get_active_schedules()` | `array` of `stdClass` | `AIPS_Schedule_Entry[]` |
| `get_active_schedules_by_template($id)` | `array` of `stdClass` | `AIPS_Schedule_Entry[]` |

¹ `get_due_schedules()` uses `SELECT t.*, s.*, s.id AS schedule_id` — the merged row places
`schedule_id` as a separate alias.  `from_row()` reads `$row->id`, so the migration must
ensure the row has `id` (schedule PK) set correctly; consumers currently access
`$schedule->schedule_id`.  Map `schedule_id` → `id` in the factory call, or add a
`$schedule_id` alias property.

### 3c. Callers: the consumers (Phase E.3 work)

| File | Method / context | Fields accessed today |
|---|---|---|
| `class-aips-schedule-processor.php` | `execute_all_due()` loop, `execute_by_id()` | `$schedule->schedule_id`, `->template_id`, `->next_run`, `->frequency`, `->name`, `->circuit_state` |
| `class-aips-schedule-processor.php` | `execute_schedule_logic()` | `->schedule_id`, `->template_id`, `->frequency`, `->post_status`, `->topic`, `->article_structure_id`, `->rotation_pattern` |
| `class-aips-schedule-controller.php` | `ajax_run_now()`, `ajax_save()`, etc. | `->template_id`, `->next_run`, `->is_active`, `->schedule_type` |
| `class-aips-unified-schedule-service.php` | `get_all_items()` (~L268), `get_by_id()` (~L226) | Various schedule fields for the unified calendar view |
| `class-aips-calendar-controller.php` | `ajax_get_events()` (~L56) | `->template_id`, `->frequency`, `->next_run`, `->is_active`, `->template_name` |
| `class-aips-dashboard-controller.php` | `ajax_get_stats()` (~L52) | `->template_name`, `->next_run`, `->frequency` |
| `class-aips-templates.php` | `ajax_get_schedules()` (~L88, L155) | `->template_id`, `->is_active`, `->frequency`, `->next_run` |
| `class-aips-template-type-selector.php` | `select_by_schedule()` (~L204) | `->article_structure_id`, `->template_id` |
| `class-aips-notifications-event-handler.php` | `handle_schedule_event()` (~L309) | `->template_id`, `->schedule_type`, `->id` |

**Priority order for E.3 schedule:** `schedule-processor` (highest traffic, cron path) →
`unified-schedule-service` → `calendar-controller` → `schedule-controller` →
`dashboard-controller` → `templates` → `template-type-selector` → `notifications-event-handler`.

---

## 4. `AIPS_Template_Data`

### 4a. What the DTO represents

An immutable value object wrapping one `aips_templates` DB row, with helpers:
- `has_title_prompt()`, `has_image_prompt()`, `has_voice()`

### 4b. Repository: the producer (Phase E.3 work)

**File:** `includes/class-aips-template-repository.php`

All read methods currently return raw `stdClass` rows or arrays thereof.

| Method | Returns today | Proposed return type |
|---|---|---|
| `get_all($active_only)` | `array` of `stdClass` | `AIPS_Template_Data[]` |
| `get_by_id($id)` | `stdClass\|null` | `AIPS_Template_Data\|null` |
| `search($term)` | `array` of `stdClass` | `AIPS_Template_Data[]` |

### 4c. Callers: the consumers (Phase E.3 work)

| File | Method / context | Fields accessed today |
|---|---|---|
| `class-aips-schedule-processor.php` | `execute_by_id()` (~L149), `execute_schedule_logic()` (~L276) | `->name`, `->prompt_template`, `->title_prompt`, `->image_prompt`, `->voice_id`, `->post_status`, `->post_category`, `->generate_featured_image`, `->featured_image_source` |
| `class-aips-generation-context-factory.php` | `build_from_history_id()` (~L88) | `->id`, `->voice_id`, `->prompt_template` |
| `class-aips-onboarding-wizard.php` | `ajax_generate_post()` (~L435), `ajax_get_state()` (~L139) | `->id`, `->name`, `->prompt_template` |
| `class-aips-research-controller.php` | `ajax_generate_from_research()` (~L502–503) | Iterates get_all(); uses `->id`, `->name` |
| `class-aips-generated-posts-controller.php` | `ajax_get_posts()` (~L173), per-post template cache (~L482) | `->name` |
| `class-aips-post-review.php` | `ajax_regenerate_post()` (~L419) | `->id`, `->prompt_template`, `->voice_id` |
| `class-aips-notifications-event-handler.php` | `handle_schedule_event()` (~L322) | `->name` |
| `class-aips-seeder-service.php` | `seed_templates()` (~L135, L173) | `->id`, `->name`, `->prompt_template` |
| `class-aips-templates-controller.php` | `ajax_generate_preview()` (~L247) | Passes `$context` wrapping template row |

**Priority order for E.3 template:** `schedule-processor` → `generation-context-factory` →
`post-review` → `onboarding-wizard` → `research-controller` → `generated-posts-controller` →
`notifications-event-handler` → `seeder-service` → `templates-controller`.

---

## 5. `AIPS_Ajax_Response` — **Fully Adopted ✅**

`AIPS_Ajax_Response::success()`, `::error()`, `::permission_denied()`, `::invalid_request()`,
and `::not_found()` are already used in all 23 AJAX controller/handler files.  There are
**zero** direct `wp_send_json_success()` or `wp_send_json_error()` calls outside the helper
class itself.

No migration work is needed for Phase E.4.

Files confirmed as already using `AIPS_Ajax_Response`:

`class-aips-admin-bar`, `class-aips-ai-edit-controller`, `class-aips-author-topics-controller`,
`class-aips-authors-controller`, `class-aips-calendar-controller`, `class-aips-data-management`,
`class-aips-db-manager`, `class-aips-dev-tools`, `class-aips-generated-posts-controller`,
`class-aips-history`, `class-aips-onboarding-wizard`, `class-aips-planner`,
`class-aips-post-review`, `class-aips-prompt-sections-controller`,
`class-aips-research-controller`, `class-aips-schedule-controller`,
`class-aips-seeder-admin`, `class-aips-settings-ajax`, `class-aips-sources-controller`,
`class-aips-structures-controller`, `class-aips-taxonomy-controller`,
`class-aips-templates-controller`, `class-aips-voices`.

---

## 6. Prioritized Migration Roadmap

### Phase E.2 — Generator flow (highest-value, single-class change)

1. Update `AIPS_Generator::generate_post_from_context()` to return `AIPS_Generation_Result`
   instead of `int|WP_Error`.
2. Keep `generate_post()` signature backward-compatible: return `int|WP_Error` via a thin
   adapter shim or update all callers in the same PR.
3. Update callers in priority order (see §2c).

**Key risk:** `get_due_schedules()` merges template and schedule columns — the `id` column
comes from the template row, not the schedule row, so `AIPS_Schedule_Entry::from_row()` would
read the wrong ID.  The SQL must alias `s.id AS id` (or `AIPS_Schedule_Entry::from_row()`
must accept an `$id_override` parameter).  This is the main migration landmine.

### Phase E.3 — Repository returns (two independent streams)

**Stream A — Schedule repository** (impacts cron, scheduler, calendar):

1. Update `get_by_id()` first (single-object, easiest to verify).
2. Update `get_all()` and `get_upcoming()` next.
3. Update `get_due_schedules()` last (requires SQL alias fix described above).

**Stream B — Template repository** (impacts generator, controllers):

1. Update `get_by_id()` first.
2. Update `get_all()` and `search()`.

### Phase E.4 — AJAX responses

Already complete.  No action needed.

---

## 7. Testing Requirements

| Phase | Tests to add / update |
|---|---|
| E.2 | Update `test-typed-dtos.php`; add integration tests in `test-schedule-processor.php` and `test-schedule-controller.php` for DTO-backed generation paths |
| E.3 (schedule) | Add `AIPS_Schedule_Entry` round-trip tests per repository method; verify `schedule-processor` cron path with DTO-typed rows |
| E.3 (template) | Add `AIPS_Template_Data` round-trip tests per repository method |
| E.4 | Already complete |

---

*Generated: Phase E.1 inventory sweep, 2026-04-12.*
