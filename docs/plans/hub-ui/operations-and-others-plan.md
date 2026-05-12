



## Phase 1 — Operations Hub only (Run 1 equivalent)
Implement these pieces only:

1. Add `operations` hub in hub registry:
   - slug `aips-operations`
   - tab `insights`
   - legacy page mapping includes `aips-operations-insights`
   - partial points to operations insights tab file.  
   (Pattern reference: hub structure in `AIPS_Admin_Hub_Registry`). ai-post-scheduler/includes/class-aips-admin-hub-registry.php:92-190

2. Add renderer method in admin menu:
   - `render_operations_hub_page()`
   - `render_operations_insights_page()` should redirect via helper logical route (`operations_insights`).  
   (Pattern reference in current menu class). ai-post-scheduler/includes/class-aips-admin-menu.php:192-197ai-post-scheduler/includes/class-aips-admin-menu.php:337-342

3. Add helper route mapping:
   - logical route `operations_insights` => `aips-operations&tab=insights`
   - keep existing behavior for all other routes untouched.  
   (Pattern reference in helper switch map). ai-post-scheduler/includes/class-aips-admin-menu-helper.php:27-57ai-post-scheduler/includes/class-aips-admin-menu-helper.php:194-197

4. Add operations hub tab partial:
   - render `AIPS_Operations_Insights_Controller`.  
   (Controller already exists). ai-post-scheduler/includes/class-aips-operations-insights-controller.php:13-38

---

## Phase 2 — UX consolidation (Run 2 equivalent)
1. Add “Operations Insights” action button in:
   - Outputs > History tab actions.
   - Settings > System Status tab actions.
2. Ensure those links call `AIPS_Admin_Menu_Helper::get_page_url('operations_insights')`.  
   (Current actions live in hub registry tab configs). ai-post-scheduler/includes/class-aips-admin-hub-registry.php:334-354ai-post-scheduler/includes/class-aips-admin-hub-registry.php:567-580

3. Ensure Operations hub tab has necessary assets:
   - Add a dedicated Operations hub constant in admin assets and enqueue condition.
   - Load Chart.js for operations insights (same chart source used elsewhere).  
   (Asset architecture reference). ai-post-scheduler/includes/class-aips-admin-assets.php:52-57ai-post-scheduler/includes/class-aips-admin-assets.php:157-159ai-post-scheduler/includes/class-aips-admin-assets.php:1160-1176

4. Register Operations controller at admin boot for export callback reliability:
   - instantiate `AIPS_Operations_Insights_Controller` in `boot_admin()`.  
   (Boot location reference). ai-post-scheduler/ai-post-scheduler.php:808-831

---

## Phase 3 — Hardening and tests (Run 3 equivalent)
1. Add/update routing tests for:
   - `operations_insights` URL mapping.
   - visible slug mapping for `aips-operations-insights` → `aips-operations`.
2. Keep existing prompt sections assertions if needed, but **do not** add Post Slices assertions.
3. Ensure no test references `post_slices` logical routes in this clean PR.

(Existing test scaffold reference). ai-post-scheduler/tests/AIPS_Admin_Hub_Routing_Test.php:25-35

---

## Phase 4 — Explicit “exclude Post Slices” rollback checklist
Before opening PR:

1. Remove/avoid these files if they appear in your branch diff:
   - `includes/class-aips-post-slices-controller.php`
   - `templates/admin/post-slices.php`
   - `templates/admin/hub/tabs/content-setup/post-slices.php`
2. Remove helper map entries for `post_slices` and any `aips-post-slices` hidden page registration.
3. Remove `aips-post-slices` from `legacy_pages`.
4. Ensure `prompt_sections` route stays on Structures/Structure Sections path (not Post Slices). ai-post-scheduler/includes/class-aips-admin-menu-helper.php:100-106

---

## Suggested PR slicing (single PR, clean commits)
1. `feat(hubs): add operations hub and legacy routing`
2. `feat(hubs): add operations cross-links and assets`
3. `test(hubs): add operations route/slug mapping coverage`
4. `docs(hubs): update migration notes (operations only, post slices excluded)`

---

## What to tell the next chat (copy/paste prompt seed)
> “Implement Operations Hub migration only on `work/menu-proposal-1`.  
> Exclude all Post Slices changes entirely.  
> Add Operations hub + legacy redirect + helper routing + assets + cross-links + tests for operations route mapping.  
> Do not touch Post Slices controller/templates/slugs/routes.”



