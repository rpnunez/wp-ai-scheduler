# NunezScheduler Agent Journal

## 2026-02-20 - Template-to-Schedule Flow Optimization
**Target Feature:** Template Wizard / Scheduling Bridge
**Improvement:** Eliminated the context-breaking page reload after template save and added a seamless bridge to the Scheduling feature. Previously, saving a template triggered `location.reload()`, forcing users to manually navigate to the Schedules page, locate their template in a dropdown, and create a schedule — a 4+ step detour. Now, saving a template presents a "Next Steps" panel offering one-click Schedule, Run Now, or Done actions. A direct "Schedule" button was also added to each template row in the listing. Additionally, the schedule page detects a `?schedule_template=` query param to auto-open the modal pre-filled.
**Bug Fix (incidental):** The `saveSchedule` JS function was missing topic, article_structure_id, and rotation_pattern fields in its AJAX payload, meaning those form values were never persisted. Fixed as part of this flow work.
**Files Modified:**
- `ai-post-scheduler/assets/js/admin.js` — Post-save actions panel, quickRunNow, initScheduleAutoOpen, saveSchedule field fix
- `ai-post-scheduler/templates/admin/templates.php` — Step 6 wizard panel, Schedule row action button
- `ai-post-scheduler/templates/admin/schedule.php` — Auto-open via preselect template data attribute
- `ai-post-scheduler/includes/class-aips-admin-assets.php` — Added schedulePageUrl to localized script data
**Outcome:** Users can now go from "Template saved" to "Scheduled" in a single click, reducing the Template→Schedule journey from 5+ manual steps to 1. The wizard's post-save panel keeps the user in-context with clear next actions, following the philosophy: "Streamline the steps required for a user to move from Template to Scheduled Post."

## 2026-02-21 - Schedule Management Flow Optimization
**Target Feature:** Scheduler
**Improvement:** Added three missing capabilities to the Schedule page that were forcing users into destructive workarounds:
1. **Edit Schedule** — Previously there was no way to modify an existing schedule; users had to delete and recreate, losing `last_run` history and schedule state. Added an Edit button per row that opens the schedule modal pre-filled with all existing values (template, frequency, start time from `next_run`, topic, article structure, rotation pattern, active status). The backend already supported updates via `save_schedule()` with an `id` field — only the UI was missing.
2. **Run Now per Schedule** — The backend `ajax_run_now` endpoint already accepted a `schedule_id` parameter to execute a specific schedule with its full context (template + structure + topic + rotation), but there was no UI trigger for it. Added a Run Now button per schedule row that uses this existing endpoint and displays the success modal with an edit link to the generated post.
3. **Toggle Status Badge Feedback** — The active/inactive toggle fired the AJAX call but never updated the visual status badge, leaving users uncertain whether the toggle took effect. Fixed `toggleSchedule` to swap the badge class (`aips-badge-success` / `aips-badge-neutral`), icon (`dashicons-yes-alt` / `dashicons-minus`), and label text in real-time. On AJAX error, the toggle is reverted to its previous state.
**Files Modified:**
- `ai-post-scheduler/templates/admin/schedule.php` — Added `data-next-run` and `data-is-active` attributes to schedule rows; added Edit and Run Now action buttons
- `ai-post-scheduler/assets/js/admin.js` — Added `editSchedule` and `runNowSchedule` functions; rewrote `toggleSchedule` with live badge/icon/text updates and error rollback; added event bindings for `.aips-edit-schedule` and `.aips-run-now-schedule`
**Outcome:** Modifying a schedule is now a 2-step action (Edit → Save) instead of a 5+ step destructive workaround (delete → open modal → re-select template → re-configure → save). Manual execution and toggle feedback are now immediate and in-context, following the philosophy: "Flow is Function — a feature that is difficult to navigate is a broken feature."
