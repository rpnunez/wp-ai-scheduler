# NunezScheduler Agent Journal

## 2026-02-09 - Template Wizard Optimization
**Target Feature:** Template Wizard
**Improvement:** Implemented a full "Test Generation" feature that allows users to preview the exact output (Title, Content, Excerpt) of a template configuration before saving or scheduling it. Previously, the "Test" button only checked the content prompt and ignored other settings like Voice, Title Prompt, and Article Structure.
**Files Modified:**
- `ai-post-scheduler/includes/class-aips-generator.php` (Added `generate_preview` method)
- `ai-post-scheduler/includes/class-aips-templates-controller.php` (Updated `ajax_test_template` to use full context)
- `ai-post-scheduler/templates/admin/templates.php` (Added "Test Generation" button and improved result modal)
- `ai-post-scheduler/assets/js/admin.js` (Updated `testTemplate` logic to send full form data)
**Outcome:** Users can now iteratively refine their templates (including Voice and Title logic) without polluting their post history or creating dummy posts, significantly improving the "Template -> Schedule" workflow efficiency.

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

## 2026-02-21 - Schedule Run Now Toast Notification (follow-up)
**Target Feature:** Scheduler (Run Now feedback)
**Improvement:** The "Run Now" button on the schedule page was firing a successful AJAX call but showing no visual feedback — the code referenced `#aips-post-success-modal` which only exists in `templates.php`, not `schedule.php`. Replaced the broken modal reference with a toast notification system. Added a global `AIPS.showToast()` utility to `admin.js` (following the existing pattern from `authors.js`) and the corresponding toast CSS to `admin.css` (available on all plugin pages). On success, the toast displays the server message plus a clickable "Edit Post" link. On failure, an error toast appears. The toast auto-dismisses after 8 seconds or can be closed manually.
**Files Modified:**
- `ai-post-scheduler/assets/js/admin.js` — Added `showToast` method to AIPS object; updated `runNowSchedule` success/error handlers to use toast instead of non-existent modal
- `ai-post-scheduler/assets/css/admin.css` — Added global toast notification styles (`#aips-toast-container`, `.aips-toast`, slide-in/out animations)
**Outcome:** Users now get immediate, non-blocking visual confirmation when a schedule executes — including a direct link to edit the generated post — without leaving the schedule page.
