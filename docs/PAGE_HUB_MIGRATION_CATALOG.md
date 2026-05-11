# Page Hub Migration Catalog (Post-Merge Audit)

## Scope and method
- Audited currently registered plugin admin pages in `AIPS_Admin_Menu`.
- Audited current Hub routing and tab/subtab mapping in `AIPS_Admin_Hub_Registry` and `AIPS_Admin_Menu_Helper`.
- Audited standalone legacy admin templates under `templates/admin/*.php` for coverage.

## Executive summary
Most major plugin pages are already routed through the Hub model by redirecting legacy slugs into a hub tab/subtab route. The primary migration gap is **Operations Insights** (`aips-operations-insights`), which is still rendered as a standalone hidden page and is not mapped into any existing Hub.

I recommend keeping existing top-level hubs and adding one **new top-level Hub** to absorb operations/monitoring UX:

- **New Hub proposal:** `Operations` (slug suggestion: `aips-operations`)

This avoids overloading `Settings`, keeps daily operator workflows separate from configuration, and gives a stable place for future queue/throughput/run-health surfaces.

---

## Catalog: page coverage status

### Already migrated into Hub UI (keep)

#### Dashboard Hub (`ai-post-scheduler`)
- Dashboard overview
- Onboarding

#### Content Setup Hub (`aips-content-setup`)
- Templates
- Voices
- Structures (with structure sections subtab)
- Prompt Blocks (legacy sections page)

#### Automation Hub (`aips-automation`)
- Schedule
- Calendar
- Authors (authors list / generation queue / author topics)
- Research (trending / gap analysis / planner)

#### Outputs Hub (`aips-outputs`)
- Content Queue (generated posts / partial generations / pending review)
- History

#### Site Context Hub (`aips-site-context`)
- Sources
- Taxonomy
- Internal Links

#### Settings Hub (`aips-settings-hub`)
- Settings (general + sub-sections)
- System status
- Utilities (seeder)
- Telemetry (conditional)
- Developer tools (conditional)

### Not yet migrated (needs action)

#### 1) Operations Insights (high priority)
- Legacy slug: `aips-operations-insights`
- Current behavior: hidden page directly rendered by `AIPS_Operations_Insights_Controller`, not redirected to a hub route.
- Recommendation: move into **new Operations Hub** as first tab.

---

## Proposed hub assignment model

## Option selected (recommended): add a new top-level "Operations" Hub

### New top-level Hub: Operations
**Purpose:** real-time/near-real-time operational observability and intervention.

**Initial tabs:**
1. **Insights** (migrates current Operations Insights page)
2. **Queue & Throughput** (future-ready shell; can start as alias/partial of existing data)
3. **Failures & Recovery** (future-ready shell for partial generations/retries/system recovery deep links)

**Why new Hub instead of Settings:**
- Settings = configuration and utilities.
- Operations = active monitoring and triage.
- Clear mental model for admins running scheduled automation daily.

---

## 3-run implementation plan

## Run 1 — Information architecture + routing scaffold
**Goal:** create the new Hub shell and map Operations Insights into it without changing core data logic.

- Add `operations` hub definition in `AIPS_Admin_Hub_Registry::get_hubs()` with at least one tab (`insights`).
- Add top-level submenu registration via existing hub loop (automatic once registry entry exists).
- Add `aips-operations-insights` to `legacy_pages` for the new hub.
- Update `AIPS_Admin_Menu_Helper` with:
  - `operations_hub` slug mapping
  - logical page mapping for `operations_insights` → `tab=insights`
- Change `render_operations_insights_page()` to `redirect_legacy_page('operations_insights')`.
- Add tab partial file under `templates/admin/hub/tabs/operations/insights.php` that reuses current controller rendering path.

**Deliverable:** no standalone Operations Insights entry point; page resolves through Hub URL and keeps behavior.

## Run 2 — UX consolidation + shared components
**Goal:** align migrated Operations tab with Hub design language and cross-linking.

- Normalize header/actions/filters into hub tab pattern used by other tabs.
- Migrate/rename the newly added **Post Slices** page into **Content Setup** Hub navigation (replace the prior Prompt Blocks naming in hub tabs and legacy redirects).
- Add cross-links from:
  - Outputs → Operations Insights
  - Settings/System Status → Operations Insights
- Audit scripts/styles enqueue conditions for operations routes in hub context.
- Verify breadcrumbs/tab states for legacy links (`aips-operations-insights`) now highlight Operations Hub.

**Deliverable:** visually and behaviorally consistent Operations experience in Hub framework.

## Run 3 — hardening, cleanup, and acceptance
**Goal:** clean legacy assumptions and lock migration.

- Remove dead standalone assumptions in any JS/CSS/page-specific conditionals.
- Add/update PHPUnit coverage for page-to-hub URL mapping and submenu highlighting logic.
- Add/update docs (`README`/feature docs/admin IA doc) with new Operations Hub location.
- Regression pass over all hidden legacy slugs to ensure they resolve to intended hub tabs.

**Deliverable:** migration complete, documented, and test-covered.

### Run 3 execution notes
- Added routing coverage tests in `tests/AIPS_Admin_Hub_Routing_Test.php` for:
  - `prompt_sections` → structures/structure-sections
  - `post_slices` → content-setup post_slices tab
  - `operations_insights` → operations insights tab
  - legacy slug → visible hub slug mapping checks.
- Added `aips-post-slices` to Content Setup hub `legacy_pages` to ensure parent/submenu highlighting stays correct for direct legacy URLs.

---

## Suggested backlog after 3 runs (optional)
- Expand Operations Hub with queue internals and retry controls.
- Add telemetry deep-linking (when telemetry enabled) from Operations metrics.
- Add role-based quick actions for common triage tasks.
