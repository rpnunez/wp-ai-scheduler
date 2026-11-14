# Admin Hub Flyout Submenus Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the in-page vertical rails on Content, Automations, Studio, Research, and Diagnostics with accessible WordPress-admin flyout submenus, remove Studio's Launchpad, and leave Settings as the only hub that retains its rail.

**Architecture:** Extend `AIPS_Admin_Page_Context::get_canonical_hubs()` with flyout-only presentation metadata, then have a small navigation-data builder export the five approved hubs to one lightweight admin script. The script owns a single body-level flyout, binds it to the existing WordPress submenu links, and handles positioning, pointer timing, keyboard navigation, and responsive suppression; page templates lose their rail wrapper and expand their existing stages to full width. Studio's root/default route becomes Templates, while a new `structure-sections` Studio route reuses the existing Structures workspace and opens its current inner Structure Sections panel directly.

**Tech Stack:** WordPress 5.8+ admin APIs, PHP 8.2+, the established jQuery/`window.AIPS` module pattern, `AIPS.Templates`, token-based CSS, Dashicons, PHPUnit 9, and the repository's lightweight Node test harness.

**Spec:** `C:/Projects/AIPS/Artifacts/AIPS hover menu redesign/design_handoff_aips_hover_menu/README.md` and `C:/Projects/AIPS/Artifacts/AIPS hover menu redesign/design_handoff_aips_hover_menu/AIPS Hover Menu.dc.html` (turn 2 / options 2a and 2b only)

## Global Constraints

- Target Content, Automations, Studio, Research, and Diagnostics; Settings remains the only hub with an in-page rail.
- Preserve all existing `admin.php?page={hub slug}&tab={section key}` URLs, controller routing, active-tab normalization, tab panels, and page headers.
- Treat the supplied HTML and bundled icon font as design references only; ship neither, because WordPress Dashicons are already available.
- Use `AIPS_Admin_Page_Context::get_canonical_hubs()` as the navigation source of truth; do not retain duplicate rail arrays in the five target templates.
- Content flyout groups are `Articles`, `Structure`, and `Index & Links`; Automations groups are `Pipelines`, `Inputs`, and `Optimization` exactly as shown in the handoff.
- Studio uses one `Create` group with exactly five flyout entries in this order: Templates, Voices, Article Structures, Structure Sections, and Post Slices.
- Remove Studio Launchpad as a page state: `admin.php?page=aips-studio`, an empty/invalid Studio tab, and the legacy `tab=launchpad` URL must resolve to Templates; remove the Launchpad cards, Launchpad-only summary calculation, and Launchpad rail entry.
- Add `structure-sections` as a canonical Studio section. It renders the existing Structures template/data and makes the existing `aips-structure-sections` inner panel active on initial render; Article Structures continues to open the `aips-structures` inner panel.
- Research uses one `Research & Planning` group with Trending Topics, Content Auditor, and Keyword Planner in the current route order; no Research functionality or tab content is redesigned.
- Diagnostics uses two columns: `System` (System Info, System Health & Tools, Operational Status, AI Errors, Insights) and `Tools & Performance` (Telemetry, Cache Monitor, Background Processes, Stress Test, Dev Tools); feature-gated items must be omitted when unavailable.
- Automations flyout omits the contextual child destinations `Author's Topics` and `Source Data`; those routes remain reachable from their parent workflows and must still resolve to the correct active parent section.
- Open after 120 ms hover intent; close after 200 ms outside both trigger and panel; open immediately on keyboard focus; Escape closes and restores focus.
- Disable flyouts at the WordPress mobile breakpoint (`max-width: 782px`) and for coarse/touch-only input, preserving ordinary parent-link navigation.
- All user-facing strings remain localized with the `ai-post-scheduler` text domain and all generated URLs/data are escaped or encoded through WordPress APIs.
- Run PHP tests only through one `make test-session ARGS="..."` invocation from `ai-post-scheduler/`, following `TESTING.md`.

## Review Focus

- A current child/leaf page (campaign detail, author's topics, source data, Studio legacy child route, Diagnostics hidden child page) must highlight the correct hub/flyout item without exposing contextual child rows.
- A feature-gated Diagnostics destination must never appear or accept keyboard focus when its feature is unavailable.
- Collapsed, auto-folded, RTL, scrolled, and viewport-edge WordPress menus must keep the panel on-screen and anchored to the actual trigger rectangle.
- Pointer travel across the trigger/panel gap, including diagonal travel, must not close the flyout before the pointer arrives.
- Long translated headings/descriptions and a multi-digit Pending Review badge must wrap without overlapping icons, columns, or the viewport.

---

## Planned File Structure

- Modify `ai-post-scheduler/includes/class-aips-admin-page-context.php`: canonical labels, missing sections, Studio's new default/Structure Sections route, flyout group/order/short-copy metadata, and active-section resolution inputs.
- Modify `ai-post-scheduler/includes/class-aips-studio-controller.php`: remove Launchpad state, make Templates the default, and route Article Structures/Structure Sections to the correct initial inner panel.
- Create `ai-post-scheduler/includes/class-aips-admin-flyout-navigation.php`: normalize the approved hub definitions, apply capability/feature gates, build URLs, active state, and optional badge data.
- Modify `ai-post-scheduler/includes/class-aips-admin-assets.php`: enqueue the dedicated flyout CSS/JS wherever the AIPS WordPress submenu is present and localize the normalized payload.
- Create `ai-post-scheduler/assets/js/admin-flyout-navigation.js`: shared jQuery/`window.AIPS` renderer and interaction state machine.
- Create `ai-post-scheduler/templates/admin/partials/admin-flyout-templates.php`: shared `<script type="text/html">` templates consumed through `AIPS.Templates`.
- Create `ai-post-scheduler/assets/css/admin-flyout-navigation.css`: isolated high-fidelity flyout styling and responsive/RTL/collapsed-menu behavior.
- Modify `ai-post-scheduler/templates/admin/content.php`, `automations.php`, `studio.php`, `research.php`, and `diagnostics.php`: remove duplicated rail construction/rendering and retain a full-width main stage; delete Studio Launchpad markup.
- Modify `ai-post-scheduler/templates/admin/structures.php`: accept an explicit initial subpanel instead of always activating Article Structures.
- Modify `ai-post-scheduler/assets/css/admin.css`: add/adjust the generic full-width hub-stage rule only if existing stage selectors depend on `.aips-rail-main`; retain the reusable rail component and its styles for Settings.
- Modify `ai-post-scheduler/tests/Test_AIPS_Admin_Page_Context.php`: registry completeness and route-resolution coverage.
- Create `ai-post-scheduler/tests/Test_AIPS_Admin_Flyout_Navigation.php`: payload, URL, availability, active state, localization, and badge coverage.
- Create `ai-post-scheduler/tests/js/admin-flyout-navigation.test.js`: rendering and interaction behavior using the existing zero-dependency DOM harness pattern.

### Task 1: Canonical flyout metadata and registry drift repair

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-admin-page-context.php`
- Modify: `ai-post-scheduler/tests/Test_AIPS_Admin_Page_Context.php`

**Interfaces:**
- Produces: optional section keys `flyout_group` and `short_description`; only sections with a non-empty `flyout_group` are public flyout entries.
- Produces: canonical Content entry `aips-broken-links` and canonical Diagnostics entry `ai-errors`.
- Preserves: existing hub keys, section keys, `default_tab`, `child_pages`, and `resolve()` behavior.

- [ ] **Step 1: Add failing registry tests**

  Add assertions that Content contains all 11 designed destinations including `aips-broken-links`; Automations exposes exactly the seven designed sections through `flyout_group`; Studio defaults to `templates` and exposes exactly Templates, Voices, Article Structures, Structure Sections, and Post Slices; Research exposes its three current destinations; Diagnostics defines the controller's public tabs and the approved two-column groups; Settings sections have no flyout metadata.

- [ ] **Step 2: Run the focused test and confirm the new assertions fail**

  Run: `make test-session ARGS="tests/Test_AIPS_Admin_Page_Context.php"`

  Expected: FAIL because the missing sections and flyout metadata are not present.

- [ ] **Step 3: Add the canonical metadata**

  Use the handoff's exact short descriptions and icons for Content/Automations. Add concise existing-product copy for Studio/Research, include Voices in Studio's `Create` group, add canonical `structure-sections`, change Studio's default to `templates`, align Diagnostics keys/labels/icons with `AIPS_Diagnostics_Controller::get_tabs()`, assign the approved groups, and leave only contextual Automations children without a group.

- [ ] **Step 4: Re-run the focused test**

  Run: `make test-session ARGS="tests/Test_AIPS_Admin_Page_Context.php"`

  Expected: PASS.

- [ ] **Step 5: Commit the registry contract**

  Commit: `feat(admin): add canonical flyout navigation metadata`

### Task 2: Server-side navigation payload builder

**Files:**
- Create: `ai-post-scheduler/includes/class-aips-admin-flyout-navigation.php`
- Create: `ai-post-scheduler/tests/Test_AIPS_Admin_Flyout_Navigation.php`
- Modify: the plugin's existing Composer/autoloader class map only if the naming convention does not already discover the new class.

**Interfaces:**
- Produces: `AIPS_Admin_Flyout_Navigation::get_payload($page_slug = null, $tab_key = null): array`.
- Payload hub shape: `slug`, `label`, `defaultUrl`, `activeSection`, and ordered `groups`.
- Payload group shape: `label` and ordered `items`.
- Payload item shape: `key`, `label`, `icon`, `description`, `url`, `active`, and optional integer `badge`.
- Consumes: `AIPS_Admin_Page_Context::get_canonical_hubs()`, `AIPS_Admin_Page_Context::resolve()`, `AIPS_Diagnostics_Controller::is_tab_available()`, and `AIPS_Post_Review_Repository::get_draft_count()`.

- [ ] **Step 1: Write failing payload tests**

  Cover the exact five-hub allow-list/order, exact group/item order, URL generation, default-tab active state, legacy Studio Launchpad resolution to Templates, Voices and Structure Sections active states, alias/child-page active resolution, omission of unavailable Diagnostics tabs and ungrouped contextual sections, and a Pending Review badge that is absent at zero and an integer when positive.

- [ ] **Step 2: Run the new test and confirm the class is missing**

  Run: `make test-session ARGS="tests/Test_AIPS_Admin_Flyout_Navigation.php"`

  Expected: FAIL because `AIPS_Admin_Flyout_Navigation` does not exist.

- [ ] **Step 3: Implement the pure payload builder**

  Normalize registry entries without rendering HTML. Resolve the current context once, intersect Diagnostics with its runtime availability checks, catch repository failures so navigation still renders without a badge, and require `manage_options` before exposing payload data.

- [ ] **Step 4: Run registry and payload tests together**

  Run: `make test-session ARGS="tests/Test_AIPS_Admin_Page_Context.php tests/Test_AIPS_Admin_Flyout_Navigation.php"`

  Expected: PASS.

- [ ] **Step 5: Commit the server-side data layer**

  Commit: `feat(admin): build flyout navigation payload`

### Task 3: Flyout renderer, interaction model, and styling

**Files:**
- Create: `ai-post-scheduler/assets/js/admin-flyout-navigation.js`
- Create: `ai-post-scheduler/assets/css/admin-flyout-navigation.css`
- Create: `ai-post-scheduler/templates/admin/partials/admin-flyout-templates.php`
- Create: `ai-post-scheduler/tests/js/admin-flyout-navigation.test.js`

**Interfaces:**
- Consumes: localized global `aipsAdminFlyouts` with `hubs`, `openDelay` = 120, `closeDelay` = 200, and breakpoint = 782.
- Binds triggers by their existing AIPS submenu `href` page slug, without rewriting or cancelling normal link navigation.
- Produces: one reusable `.aips-admin-flyout` appended to `document.body`; `destroy()` is exposed only for tests/cleanup.

- [ ] **Step 1: Add failing JavaScript behavior tests**

  Test exact grouped markup, active item and badge, delayed hover open/close, cancellation while crossing into the panel, focus open, ArrowUp/ArrowDown/Home/End movement, cross-column ArrowLeft/ArrowRight movement, Escape close/focus restoration, outside focus close, missing trigger tolerance, mobile/coarse-pointer suppression, and viewport/RTL positioning.

- [ ] **Step 2: Run the repository's JavaScript test command for the new file**

  Use the same Node invocation documented by the existing `tests/js/pending-review-selection.test.js` harness.

  Expected: FAIL because the renderer is not implemented.

- [ ] **Step 3: Implement the state machine and semantic markup**

  Implement the component as a jQuery IIFE on `window.AIPS.AdminFlyoutNavigation`. Render a single panel on demand through `AIPS.Templates.render()` / `renderRaw()` and shared `<script type="text/html">` templates—never HTML string concatenation. Use a non-modal navigation/menu pattern with a labelled container, links that remain native navigation targets, roving `tabindex`, and `aria-current="page"` on the active item. Keep the trigger link clickable; do not add `aria-haspopup` until a matching payload exists.

- [ ] **Step 4: Implement safe positioning and pointer intent**

  Read the trigger's `getBoundingClientRect()` for every open/reflow, account for `#adminmenuwrap` collapsed/auto-fold state, clamp top/bottom/right edges to the viewport, support RTL by opening on the available side, and keep both trigger and panel inside the same delayed-close state.

- [ ] **Step 5: Add isolated high-fidelity CSS**

  Implement the supplied border, shadow, two 218 px columns, typography, item states, badge, focus-visible outline, wrapping, and `prefers-reduced-motion`. At `max-width: 782px` and on coarse/touch-only input, hide/disable the flyout without affecting WordPress links.

- [ ] **Step 6: Re-run JavaScript tests**

  Expected: all new JavaScript tests PASS.

- [ ] **Step 7: Commit the client component**

  Commit: `feat(admin): add accessible hub flyout component`

### Task 4: Asset loading and WordPress admin integration

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-admin-assets.php`
- Modify: `ai-post-scheduler/tests/Test_AIPS_Admin_Flyout_Navigation.php`

**Interfaces:**
- Consumes: `AIPS_Admin_Flyout_Navigation::get_payload()`.
- Produces: script handle `aips-admin-flyout-navigation`, style handle `aips-admin-flyout-navigation`, and localized object `aipsAdminFlyouts`.

- [ ] **Step 1: Add failing enqueue/localization tests**

  Assert the lightweight assets load for administrators on ordinary wp-admin screens where the AIPS submenu exists, do not load for users without `manage_options`, do not pull in the full plugin `admin.css` on unrelated screens, and carry only the five approved hubs.

- [ ] **Step 2: Run the server-side flyout test**

  Run: `make test-session ARGS="tests/Test_AIPS_Admin_Flyout_Navigation.php"`

  Expected: FAIL on missing handles/localized payload.

- [ ] **Step 3: Add a dedicated enqueue path**

  Register/enqueue the flyout assets independently of `enqueue_global_assets()`, localize the normalized payload and timing/breakpoint constants, and preserve the existing history/AI-edit behavior on native post screens.

- [ ] **Step 4: Run the server-side flyout test again**

  Expected: PASS.

- [ ] **Step 5: Commit WordPress integration**

  Commit: `feat(admin): load hub flyouts across wp-admin`

### Task 5: Remove Content, Automations, Research, and Diagnostics rails

**Files:**
- Modify: `ai-post-scheduler/templates/admin/content.php`
- Modify: `ai-post-scheduler/templates/admin/automations.php`
- Modify: `ai-post-scheduler/templates/admin/research.php`
- Modify: `ai-post-scheduler/templates/admin/diagnostics.php`
- Modify: `ai-post-scheduler/assets/css/admin.css`
- Modify: `ai-post-scheduler/tests/Test_AIPS_Admin_UI_Primitives.php` or create a focused template-rendering test if current coverage cannot assert the shells.

**Interfaces:**
- Consumes: existing controller variables and tab render methods unchanged.
- Produces: `.aips-hub-main` full-width stage wrapper for Content, Automations, Research, and Diagnostics; Studio adopts the same wrapper in Task 6.
- Preserves: `AIPS_Admin_UI_Primitives::render_rail()` and the reusable rail CSS for Settings.

- [ ] **Step 1: Add failing template/shell assertions**

  Assert these four target pages no longer output `.aips-rail-sidebar`/`.aips-rail-layout`, still output their active tab content, and Settings still renders its rail. Add a regression assertion for the Content Silos URL while removing the duplicated rail array.

- [ ] **Step 2: Run focused PHP tests**

  Run: `make test-session ARGS="tests/Test_AIPS_Admin_UI_Primitives.php tests/Test_AIPS_Admin_Page_Context.php tests/Test_AIPS_Admin_Flyout_Navigation.php"`

  Expected: FAIL until the templates stop rendering rails.

- [ ] **Step 3: Simplify Content**

  Delete `$rail_items` and rail rendering, retain active-tab normalization and all tab panels, and wrap them in `.aips-hub-main`. Do not change tab IDs or `hidden`/`aria-hidden` behavior.

- [ ] **Step 4: Simplify Automations and Diagnostics**

  Delete their rail-item mapping/rendering and call the existing controller tab renderers inside `.aips-hub-main`; apply the same conversion to Research while preserving its current server-rendered panels and active-tab logic.

- [ ] **Step 5: Add the full-width stage rule**

  Move only selectors that target `.aips-rail-main` for these stages onto `.aips-hub-main`; do not delete the generic rail component or its responsive styles.

- [ ] **Step 6: Re-run focused PHP tests**

  Expected: PASS, including the Settings rail regression.

- [ ] **Step 7: Commit page conversion**

  Commit: `refactor(admin): replace target hub rails with flyouts`

### Task 6: Remove Studio Launchpad and add direct Structure Sections routing

**Files:**
- Modify: `ai-post-scheduler/includes/class-aips-studio-controller.php`
- Modify: `ai-post-scheduler/templates/admin/studio.php`
- Modify: `ai-post-scheduler/templates/admin/structures.php`
- Modify: `ai-post-scheduler/includes/class-aips-admin-menu-helper.php` if a logical `structure_sections` URL alias is needed by existing callers.
- Modify: `ai-post-scheduler/tests/Test_AIPS_Admin_Page_Context.php`
- Create or modify: the focused Studio controller/template test file used by the suite.

**Interfaces:**
- Produces: `AIPS_Studio_Controller::get_active_section_key(): string` returning `templates` for an empty, invalid, or legacy `launchpad` value.
- Produces: canonical Studio route `admin.php?page=aips-studio&tab=structure-sections`.
- Consumes: `render_section_content('structure-sections')`, which loads the same repositories/template as `structures` but supplies initial subpanel `aips-structure-sections`; `structures` supplies `aips-structures`.
- Preserves: the existing `voices` route and legacy hidden `aips-voices` redirect, both resolving to the Voices item in the flyout.

- [ ] **Step 1: Add failing Studio routing and rendering tests**

  Assert the root, invalid tab, and `tab=launchpad` all select Templates; Voices is present and active for both its canonical and legacy hidden route; `structures` and `structure-sections` are distinct active flyout routes; both load the Structures workspace; each marks only its corresponding inner tab/panel active; the Studio template has no Launchpad grid or rail.

- [ ] **Step 2: Run focused Studio tests and confirm failures**

  Run: `make test-session ARGS="tests/Test_AIPS_Admin_Page_Context.php tests/Test_AIPS_Admin_Flyout_Navigation.php <existing-or-new-studio-test-file>"`

  Expected: FAIL because Launchpad is still the default and Structure Sections is not a route.

- [ ] **Step 3: Make Templates the Studio default**

  Remove `launchpad` from `get_tabs()`, normalize empty/invalid/legacy Launchpad requests to `templates`, delete Launchpad-only summary/action branching, and ensure the root header/context represents Templates.

- [ ] **Step 4: Delete Launchpad presentation**

  Remove the cards/stats grid and rail construction from `studio.php`; render the selected workspace directly inside `.aips-hub-main`.

- [ ] **Step 5: Add Structure Sections initial-state routing**

  Route `structure-sections` through the existing Structures repositories/template. Pass a sanitized initial-subpanel value and render matching `active`, `hidden`, `aria-selected`, and `aria-hidden` states server-side so the correct table is visible before JavaScript runs; retain the existing inner-tab switching behavior after load.

- [ ] **Step 6: Re-run focused Studio tests**

  Expected: PASS.

- [ ] **Step 7: Commit Studio conversion**

  Commit: `refactor(admin): replace Studio launchpad with flyout routes`

### Task 7: Accessibility, responsive, visual, and regression verification

**Files:**
- Modify only files from Tasks 1-5 if verification finds defects.
- Update: `ai-post-scheduler/CHANGELOG.md` under the current `3.7.24` development entry if this repository's release convention requires user-facing UI changes there.

**Interfaces:**
- Verifies the complete feature; introduces no new interface.

- [ ] **Step 1: Run all focused automated tests in one supported session**

  Run: `make test-session ARGS="tests/Test_AIPS_Admin_Page_Context.php tests/Test_AIPS_Admin_Flyout_Navigation.php tests/Test_AIPS_Admin_UI_Primitives.php tests/Test_AIPS_Automations_Controller.php tests/Test_AIPS_Diagnostics_Controller.php <existing-or-new-studio-test-file>"`

  Expected: PASS; if controller filenames differ, substitute the existing files that cover those classes in the same single invocation.

- [ ] **Step 2: Run the JavaScript tests**

  Run the existing Node harness for `tests/js/admin-flyout-navigation.test.js` and the pre-existing pending-review JavaScript test.

  Expected: PASS.

- [ ] **Step 3: Run focused syntax/static checks**

  Run `php -l` inside the supported container/test workflow for every touched PHP file, plus the repository's configured coding-standard command if available.

- [ ] **Step 4: Browser-check expanded WordPress admin**

  Verify Content, Automations, Studio, Research, and Diagnostics from both plugin and unrelated wp-admin screens; confirm exact grouping/copy/icons, current item, Pending Review badge, 120/200 ms intent behavior, full-width page stages, and ordinary parent-link clicks.

- [ ] **Step 5: Browser-check accessibility and edge layouts**

  Verify keyboard-only opening/navigation/Escape/focus return, visible focus, screen-reader labels/current state, WP collapsed menu, auto-fold at narrower desktop widths, RTL, zoom to 200%, long localized text simulation, scroll/reposition, viewport-edge clamping, and no flyout at 782 px or below/touch input.

- [ ] **Step 6: Regression-check retained rails and routes**

  Confirm Settings is the only remaining rail; confirm Studio root/legacy Launchpad opens Templates, Studio Voices appears and is selected for both canonical and legacy routes, and both Structures destinations open the correct inner panel; confirm Automations child/detail routes and Diagnostics feature-gated/hidden pages resolve and highlight their parent destination; confirm all 11 Content tabs and all three Research tabs load from flyout URLs.

- [ ] **Step 7: Update changelog and commit verification fixes**

  Commit: `docs: note admin hub flyout navigation`

## Out of Scope

- Redesigning or removing the Settings rail.
- Redesigning what appears inside any destination. For example, the Content Indexer, Research forms, Diagnostics tools, and Studio editors keep their current tables, forms, buttons, AJAX actions, repositories, page headers, and breadcrumbs. The only content-level exception is the minimum state plumbing needed to open the existing Structure Sections panel from its new direct flyout URL.
- Rebuilding WordPress's main left-hand AI Post Scheduler menu. The existing Dashboard/Automations/Studio/Research/Content/History/Settings/Diagnostics entries remain registered as they are; this feature attaches a flyout to five of those existing links rather than adding, removing, or reordering the primary entries.
- Copying the supplied prototype into production. `AIPS Hover Menu.dc.html` is a visual demonstration containing design-tool-only markers such as `sc-for` and `{{ ... }}`; the plugin will recreate the result in its own PHP, JavaScript, and CSS. The supplied icon-font files are also unnecessary because the design uses Dashicons already shipped and loaded by WordPress.
- Turning tabs into an in-place single-page application. Clicking a flyout item continues to load a normal WordPress admin URL such as `admin.php?page=aips-research&tab=planner`; PHP renders that destination on the server. The flyout itself is interactive JavaScript, but it does not fetch and swap the destination's page content with AJAX.
