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
