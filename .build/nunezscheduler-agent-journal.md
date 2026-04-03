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

## 2026-02-21 - Schedule Run Now Toast Notification (follow-up)
**Target Feature:** Scheduler (Run Now feedback)
**Improvement:** The "Run Now" button on the schedule page was firing a successful AJAX call but showing no visual feedback — the code referenced `#aips-post-success-modal` which only exists in `templates.php`, not `schedule.php`. Replaced the broken modal reference with a toast notification system. Added a global `AIPS.showToast()` utility to `admin.js` (following the existing pattern from `authors.js`) and the corresponding toast CSS to `admin.css` (available on all plugin pages). On success, the toast displays the server message plus a clickable "Edit Post" link. On failure, an error toast appears. The toast auto-dismisses after 8 seconds or can be closed manually.
**Files Modified:**
- `ai-post-scheduler/assets/js/admin.js` — Added `showToast` method to AIPS object; updated `runNowSchedule` success/error handlers to use toast instead of non-existent modal
- `ai-post-scheduler/assets/css/admin.css` — Added global toast notification styles (`#aips-toast-container`, `.aips-toast`, slide-in/out animations)
**Outcome:** Users now get immediate, non-blocking visual confirmation when a schedule executes — including a direct link to edit the generated post — without leaving the schedule page.

## 2026-02-21 - Post Review Flow Optimization
**Target Feature:** Post Review
**Improvement:** Implemented a "Quick Preview" feature for draft posts, allowing users to review generated content (Title, Excerpt, Body, Featured Image) in a modal without leaving the admin page. Previously, reviewing a draft required opening it in a new tab via the "Edit" link.
**Details:**
- Added a "Preview" button to the actions list in both "Generated Posts" (Pending Review tab) and "Post Review" pages.
- Created a new AJAX endpoint `aips_get_draft_post_preview` to securely fetch draft content.
- Refactored `admin-post-review.js` to use the shared `AIPS.showToast` notification system, replacing inconsistent legacy notices.
- Improved feedback for actions (Publish, Delete, Regenerate) with clear toast messages.
- Updated the "Regenerate" flow to explicitly inform the user that regeneration has started and they can check History, rather than silently removing the row.
**Files Modified:**
- `ai-post-scheduler/includes/class-aips-post-review.php` — Added AJAX endpoint
- `ai-post-scheduler/includes/class-aips-admin-assets.php` — Added localization strings
- `ai-post-scheduler/templates/admin/generated-posts.php` — Added Preview button and modal support
- `ai-post-scheduler/templates/admin/post-review.php` — Added Preview button and modal support
- `ai-post-scheduler/assets/js/admin-post-review.js` — Refactored logic and implemented preview
**Outcome:** Reviewing and managing AI-generated drafts is now significantly faster and more fluid, with consistent visual feedback and no need for tab switching.

## 2026-02-20 - Template Wizard Optimization
**Target Feature:** Template Wizard
**Improvement:** Implemented a full "Test Generation" feature that allows users to preview the exact output (Title, Content, Excerpt) of a template configuration before saving or scheduling it. Previously, the "Test" button only checked the content prompt and ignored other settings like Voice, Title Prompt, and Article Structure.
**Files Modified:**
- `ai-post-scheduler/includes/class-aips-generator.php` (Added `generate_preview` method)
- `ai-post-scheduler/includes/class-aips-templates-controller.php` (Updated `ajax_test_template` to use full context)
- `ai-post-scheduler/templates/admin/templates.php` (Added "Test Generation" button and improved result modal)
- `ai-post-scheduler/assets/js/admin.js` (Updated `testTemplate` logic to send full form data)
**Outcome:** Users can now iteratively refine their templates (including Voice and Title logic) without polluting their post history or creating dummy posts, significantly improving the "Template -> Schedule" workflow efficiency.

## 2026-02-21 - Schedule Run Now Toast Notification (follow-up)
**Target Feature:** Scheduler (Run Now feedback)
**Improvement:** The "Run Now" button on the schedule page was firing a successful AJAX call but showing no visual feedback — the code referenced `#aips-post-success-modal` which only exists in `templates.php`, not `schedule.php`. Replaced the broken modal reference with a toast notification system. Added a global `AIPS.showToast()` utility to `admin.js` (following the existing pattern from `authors.js`) and the corresponding toast CSS to `admin.css` (available on all plugin pages). On success, the toast displays the server message plus a clickable "Edit Post" link. On failure, an error toast appears. The toast auto-dismisses after 8 seconds or can be closed manually.
**Files Modified:**
- `ai-post-scheduler/assets/js/admin.js` — Added `showToast` method to AIPS object; updated `runNowSchedule` success/error handlers to use toast instead of non-existent modal
- `ai-post-scheduler/assets/css/admin.css` — Added global toast notification styles (`#aips-toast-container`, `.aips-toast`, slide-in/out animations)
**Outcome:** Users now get immediate, non-blocking visual confirmation when a schedule executes — including a direct link to edit the generated post — without leaving the schedule page.

## 2026-02-21 - Component Regeneration Service Optimization
**Target Feature:** Component Regeneration
**Improvement:** Refactored the `AIPS_Component_Regeneration_Service` to significantly reduce its high coupling and improve its adherence to the Single Responsibility Principle. The original class had 16 dependencies, making it difficult to maintain and test.
**Changes:**
1.  **Created `AIPS_Generation_Context_Factory`**: Extracted the complex logic for building generation context objects from a history ID into a new factory class. This removed 4 repository dependencies from the service.
2.  **Moved `get_component_revisions`**: Relocated the database query for component revisions from the service to `AIPS_History_Repository`, centralizing data access logic.
3.  **Removed Unused Dependency**: Eliminated the unused `AIPS_History_Service` from the constructor.
**Files Modified:**
- `wp-ai-scheduler/ai-post-scheduler/includes/class-aips-component-regeneration-service.php`
- `wp-ai-scheduler/ai-post-scheduler/includes/class-aips-history-repository.php`
- `wp-ai-scheduler/ai-post-scheduler/includes/class-aips-ai-edit-controller.php`
- `wp-ai-scheduler/ai-post-scheduler/includes/class-aips-generated-posts-controller.php`
**Files Added:**
- `wp-ai-scheduler/ai-post-scheduler/includes/class-aips-generation-context-factory.php`
**Outcome:** The `AIPS_Component_Regeneration_Service` is now a much leaner orchestrator, delegating data fetching and object creation to specialized classes. This improves code clarity, testability, and maintainability, aligning with the "Flow is Function" philosophy by ensuring the underlying code structure is logical and efficient.
**Verification Status:** Blocked. The project's test suite requires a Docker environment, which was not running or accessible. Multiple attempts to run the tests via `make test` and `docker-compose exec` failed. A final attempt to run `phpunit` directly on the host failed due to missing WordPress and database dependencies. The refactoring is complete, but could not be verified.

## 2026-03-06 - Template Wizard Optimization
**Target Feature:** Template Wizard
**Improvement:** Optimized the workflow from saving a template to scheduling it by introducing a `quickSchedule` action. Now, clicking "Schedule This Template" inside the "Next Steps" wizard directs to the schedules page and immediately triggers the "Add New Schedule" modal with the template field pre-selected.
**Files Modified:** ai-post-scheduler/assets/js/admin.js
**Outcome:** Enhances efficiency for the user by streamlining the multi-step navigation process directly to task execution context.

## 2026-02-09 - Template Wizard Optimization
**Target Feature:** Template Wizard
**Improvement:** Optimized flow by allowing users to save the template from any step of the wizard, eliminating the need to click "Next" multiple times to reach the final summary step. Includes cross-step validation to guide users back to the exact step containing missing required fields.
**Files Modified:**
- `ai-post-scheduler/templates/admin/templates.php`
- `ai-post-scheduler/assets/js/admin.js`
**Outcome:** Saves experienced users time and friction when making minor updates or quickly iterating on template parameters.

## 2026-03-11 - Dashboard KPIs Optimization
**Target Feature:** Dashboard
**Improvement:** Optimized the Dashboard flow by introducing new key metrics ("Pending Reviews", "Topics in Queue", and "Partial Generations") and making all KPI summary cards clickable. Previously, the dashboard displayed read-only metrics, forcing users to manually navigate to other sections via the sidebar to take action. Now, the KPI cards serve as direct links to their respective management pages.
**Files Modified:**
- `ai-post-scheduler/includes/class-aips-history-repository.php`
- `ai-post-scheduler/includes/class-aips-author-topics-repository.php`
- `ai-post-scheduler/includes/class-aips-dashboard-controller.php`
- `ai-post-scheduler/templates/admin/dashboard.php`
**Outcome:** Enhances navigation efficiency by turning the static dashboard into an actionable launchpad, reducing the steps required to manage pending tasks and errors.

## 2026-03-06 - Template Wizard Optimization
**Target Feature:** Template Wizard
**Improvement:** Optimized the workflow from saving a template to scheduling it by introducing a `quickSchedule` action. Now, clicking "Schedule This Template" inside the "Next Steps" wizard directs to the schedules page and immediately triggers the "Add New Schedule" modal with the template field pre-selected.
**Files Modified:** ai-post-scheduler/assets/js/admin.js
**Outcome:** Enhances efficiency for the user by streamlining the multi-step navigation process directly to task execution context.

## 2026-02-09 - Template Wizard Optimization
**Target Feature:** Template Wizard
**Improvement:** Optimized flow by allowing users to save the template from any step of the wizard, eliminating the need to click "Next" multiple times to reach the final summary step. Includes cross-step validation to guide users back to the exact step containing missing required fields.
**Files Modified:**
- `ai-post-scheduler/templates/admin/templates.php`
- `ai-post-scheduler/assets/js/admin.js`
**Outcome:** Saves experienced users time and friction when making minor updates or quickly iterating on template parameters.

## 2026-03-08 - Article Structures Scheduling Flow Optimization
**Target Feature:** Article Structures
**Improvement:** Optimized flow by adding a quick-action "Schedule" button directly on the Article Structures admin list. Clicking this button redirects the user to the Schedule page, automatically opens the "Add New Schedule" modal, and pre-selects the chosen Article Structure via the `schedule_structure` query parameter. This eliminates the manual steps of navigating to the schedule page, opening the modal, and locating the structure in the dropdown.
**Files Modified:**
- `ai-post-scheduler/templates/admin/structures.php` (Added Schedule button)
- `ai-post-scheduler/templates/admin/schedule.php` (Added data-preselect-structure attribute to modal)
- `ai-post-scheduler/assets/js/admin.js` (Updated `initScheduleAutoOpen` to handle `schedule_structure` param and clean URL)
**Outcome:** Significantly reduces friction for users who create a new Article Structure and immediately want to schedule it, aligning with the "Flow is Function" philosophy by reducing a multi-step process to a single click.

## 2026-03-14 - Template Wizard Optimization
**Target Feature:** Template Wizard
**Improvement:** Optimized the `saveDraftTemplate` flow so that users can save a draft without losing their place in the wizard. Previously, saving a draft triggered a full page reload. Now, the draft is saved via AJAX, the `#template_id` is updated silently, and a success toast is shown, allowing the user to continue editing the template seamlessly.
**Files Modified:** ai-post-scheduler/assets/js/admin.js
**Outcome:** Significantly improves workflow efficiency by keeping the user in the context of the wizard after saving their progress.
## 2025-02-23 - Schedule Savings Optimization
**Target Feature:** Scheduler
**Improvement:** Optimized the flow of creating and updating a schedule. Previously, saving a schedule would trigger a full page reload (`location.reload()`), disrupting user flow and losing UI state (such as scroll position or modal status). The `saveSchedule` function has been enhanced to issue a success toast, close the modal seamlessly, and dynamically refresh the schedule table using an AJAX fetch (`$.get(location.href)`) combined with `.replaceWith()`.
**Files Modified:** `ai-post-scheduler/assets/js/admin.js`
**Outcome:** Enhances the user's workflow by creating a seamless, single-page application feel when modifying schedules, reducing disruptive flashes and context loss.

## 2026-03-15 - Template Wizard Optimization
**Target Feature:** Template Wizard
**Improvement:** Enabled clickable progress indicator steps in the Template Wizard to allow non-linear navigation.
**Files Modified:** `ai-post-scheduler/assets/js/admin.js`, `ai-post-scheduler/assets/css/admin.css`
**Outcome:** Reduces friction for users, allowing them to jump directly to previous steps or skip ahead (if intermediate steps are valid) without needing to click "Next" or "Back" multiple times, significantly improving the edit flow.

## 2024-03-24 - Dashboard Optimization
**Target Feature:** Dashboard
**Improvement:** Replaced hardcoded admin URLs with `AIPS_Admin_Menu_Helper::get_page_url()` in dashboard templates and related notification classes.
**Files Modified:** `ai-post-scheduler/templates/admin/dashboard.php`, `ai-post-scheduler/includes/class-aips-partial-generation-notifications.php`, `ai-post-scheduler/tests/test-partial-generation-notifications.php`, `ai-post-scheduler/tests/test-post-review-notifications.php`
**Outcome:** Improved routing maintainability and eliminated hardcoded URLs.

## 2026-03-24 - Multiple Posts Per Run Optimization
**Target Feature:** Scheduler
**Improvement:** Implemented "Multiple Posts Per Run" allowing a single schedule execution to generate a configurable number of posts. Previously, schedules would only generate a single post, ignoring the `post_quantity` configuration on the parent Template. The `AIPS_Schedule_Processor::execute_schedule_logic` was updated to explicitly retrieve the template, respect its `post_quantity`, and loop the `generate_post` call. The resulting array of IDs was then gracefully propagated back through cleanup functions, logging utilities, and the controller's AJAX responses, maintaining backward compatibility while drastically improving throughput for high-volume publishing workflows.
**Files Modified:**
- `ai-post-scheduler/includes/class-aips-schedule-processor.php`
- `ai-post-scheduler/includes/class-aips-schedule-controller.php`
- `ai-post-scheduler/tests/test-manual-schedule-execution.php`
**Outcome:** Enhances scheduling efficiency for users managing high-volume blogs by allowing batch generation through a single scheduled task rather than forcing them to configure multiple identical schedules.
  
## 2026-03-24 - Planner Optimization
**Target Feature:** Planner
**Improvement:** Optimized the Planner UI flow by adding an inline remove button (an X icon) directly to each generated topic row (`.topic-item`). This allows users to quickly delete individual topics without needing to manually clear the input field or use bulk actions. Added `removeTopic` logic to `admin-planner.js` which fades out the item, updates the selection count, and gracefully hides the panel if it was the last topic.
**Files Modified:**
- `ai-post-scheduler/assets/js/admin-planner.js`
- `ai-post-scheduler/assets/css/planner.css`
**Outcome:** Greatly enhances the speed and fluidity of curating brainstormed topic lists by allowing one-click removal of unwanted suggestions.
## 2026-03-22 - Planner "Generate Now" Optimization
**Target Feature:** Planner
**Improvement:** Optimized the workflow for generating content from brainstormed topics by adding a "Generate Now" button next to "Schedule Selected Topics". Previously, users who wanted to immediately generate a post for a brainstormed topic had to navigate away to another tool, or manually schedule it for "now". Now, clicking "Generate Now" bypasses the schedule and immediately generates posts for the selected topics within the same request flow.
**Files Modified:**
- `ai-post-scheduler/templates/admin/planner.php`
- `ai-post-scheduler/assets/js/admin-planner.js`
- `ai-post-scheduler/includes/class-aips-planner.php`
**Outcome:** Enhances efficiency for the user by streamlining the multi-step navigation process directly to task execution, fulfilling the "from research to schedule in one flow" shortcut.

## 2026-03-25 - Planner Optimization
**Target Feature:** Planner (Topic Brainstorming and Bulk Scheduling/Generation)
**Improvement:** Optimized the flow by not clearing the entire list of topics upon bulk generation or scheduling. Instead, the UI now removes only the successfully processed topics. The results panel is only hidden when all selected topics are consumed. This allows users to generate or schedule partial batches without losing their work.
**Files Modified:** ai-post-scheduler/assets/js/admin-planner.js
**Outcome:** Users can now incrementally process brainstormed topics (e.g., generating some immediately and scheduling others) without their remaining topics disappearing unexpectedly, preserving their workflow.

## 2026-03-26 - Templates Controller Optimization
**Target Feature:** Templates Controller
**Improvement:** Optimized the flow and reliability of the template management process by enforcing early input validation across all key AJAX endpoints (`ajax_save_template`, `ajax_test_template`, `ajax_preview_template_prompts`, `ajax_clone_template`, `ajax_delete_template`, `ajax_get_template`). Validations included robust checks for required inputs, type coercion limits, integer boundary limits, and trimming of empty string values.
**Files Modified:** `ai-post-scheduler/includes/class-aips-templates-controller.php`
**Outcome:** Prevents users from accidentally saving corrupt templates with empty prompts or invalid names, increasing the robustness and flow of the template wizard experience by providing clear, immediate backend error messages instead of failing silently or proceeding with incomplete data.

## 2026-03-31 - Article Structures Flow Optimization
**Target Feature:** Article Structures
**Improvement:** Optimized the save flow for Article Structures and Prompt Sections. Previously, saving either entity triggered a full page reload (`location.reload()`), disrupting the user flow and causing context loss. The save functions now issue a success toast, seamlessly close the modal, and dynamically refresh the respective table (and select dropdowns) using an AJAX fetch.
**Files Modified:** `ai-post-scheduler/assets/js/admin.js`
**Outcome:** Enhances the user's workflow by creating a seamless, single-page application feel when modifying structures and sections, eliminating disruptive flashes and improving overall administrative efficiency.
## 2026-02-09 - Planner Optimization
**Target Feature:** Planner
**Improvement:** Optimized the scheduling and generating flow by adding localStorage persistence for `template_id` and `frequency`. This prevents users from having to reselect these dropdowns on consecutive bulk actions. Added smooth scrolling to the `#planner-results` panel after generating or parsing manual topics, significantly improving context focus and UX flow.
**Files Modified:** `ai-post-scheduler/assets/js/admin-planner.js`
**Outcome:** The Planner requires fewer manual steps to bulk schedule topics and guides the user smoothly down the page after topic generation.
