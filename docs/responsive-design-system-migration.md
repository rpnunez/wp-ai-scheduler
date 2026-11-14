# Responsive Admin UI: Shared Systems & Migration Tracker

**Scope:** `ai-post-scheduler/assets/css/admin-responsive.css` + related classes in `admin.css`, and every admin template/table that could adopt them.
**Status:** Living document — update it in the same PR whenever a system ships or a page adopts one. Don't let this drift the way `docs/css-variables-audit.md` found the token system drifting.

## Why this exists

The History admin page was unusable on phone-width screens (heavy horizontal scroll on its table, a broken modal layout). Rather than a one-off fix, the fix was built as a set of small, opt-in, reusable classes — tried out on History first — so the other ~30 admin templates can adopt the same classes later instead of each growing its own page-specific `@media` block. This doc tracks what's shipped, what's safe to reuse where, and what's still open.

History (its table, filter bar, buttons, and both modals) remains the **reference implementation** — the place new systems get built and proven before any other page adopts them.

---

## 1. Systems ledger

| System | Class(es) | Status | Lives in | Notes |
|---|---|---|---|---|
| Table → stacked cards | `.aips-table-responsive` (+ `data-label` on each `<td>`) | ✅ Shipped | `admin-responsive.css` | Below 600px. No column is ever hidden — everything shows, relabeled. **Never apply to `AIPS_List_Table` pages** — see §3, core already handles their responsiveness differently. |
| Fullscreen modal | `.aips-modal-fullscreen-mobile` | ✅ Shipped | `admin-responsive.css` | Edge-to-edge below 600px. Applied to History's logs-detail drawer only so far. Safe to apply to any `.aips-modal-content`. |
| Scrollable tabs | `.aips-tabs-scrollable` | ✅ Shipped | `admin-responsive.css` | Horizontal-scroll pill strip below 600px. Applied to History's detail-modal tab nav (Overview/Timeline/AI Calls/Technical). |
| Filter bar stacking | `.aips-filter-bar` + `.aips-history-filter-selects`/`.aips-history-filter-search`/`.aips-history-filter-row-dates` inner markup | ✅ Shipped (fixed + generalized) | `admin-responsive.css` | Below 782px, selects/inputs go full-width. A prior version of this rule (`admin.css`) targeted classes that didn't exist anywhere and never fired — fixed as part of this round. Scoped to the already-generic `.aips-filter-bar` wrapper, so it reaches **History, Sources, and Source Data for free** (they already share this exact inner markup) with zero template changes. |
| Touch targets (44px) | applies automatically to `.aips-btn-sm`, `.aips-row-action-overflow-toggle`, `.aips-history-page-nav.button`, checkbox hit-area | ✅ Shipped | `admin-responsive.css` | Below 600px only; desktop sizing untouched. Uses the existing `--aips-control-h-lg` (44px) token — no new token needed. |
| Row-action kebab/overflow menu | `.aips-row-action-menu` / `.aips-row-action-overflow-toggle` / `.aips-row-action-item` | ✅ Already global (not new) | JS: `assets/js/admin.js:369-372,4653-4681` · CSS: `admin.css:1231-1313` | **Not built this round — already shared.** History uses it; the 6 `AIPS_List_Table` pages don't (they use WP core's hover-only `row_actions()`, which doesn't work on touch devices). Adopting it elsewhere is a per-page PHP change (replace/augment `row_actions()` output), not new CSS/JS. See decision log. |
| Pagination | `.aips-history-pagination` | 🔴 Not generalized | `admin.css` | **Do not reuse the bare name `.aips-pagination`** — it already exists, independently, on the Link Report page (`admin-link-report.css`, scoped `.aips-link-report-tab .aips-pagination`) with different styling (`justify-content: flex-end`, bordered). Any future shared pagination component needs a distinct name (e.g. `.aips-pagination-bar`) to avoid a third silent collision. The 6 `AIPS_List_Table` pages render WP core's native `.tablenav-pages` pagination untouched. See decision log for the recommended approach. |

## 2. Breakpoints & tokens

- `--aips-bp-tablet: 782px` / `--aips-bp-mobile: 600px` — documented in `design-tokens.css`. Copy the literal px value into new `@media` queries (custom properties don't work inside `@media` feature queries).
- `--aips-control-h-lg: 44px` — already existed, now also the touch-target floor on mobile.
- New admin-wide responsive rules belong in `admin-responsive.css`, never a new page-specific `@media` block in `admin.css`.

## 3. Page inventory

| Page | Table system | Filter UI | Row actions | Pagination | Mobile table status |
|---|---|---|---|---|---|
| **History** (pilot) | custom `<table class="aips-table">` | custom `.aips-filter-bar` | kebab menu | `.aips-history-pagination` | ✅ `.aips-table-responsive` applied |
| Sources | `AIPS_List_Table` → core `WP_List_Table` | custom `.aips-filter-bar` (shared markup — gets the filter-bar fix free) | core `row_actions()` | core native | Core's own 782px `data-colname` stacking (untouched, don't add `.aips-table-responsive`) |
| Source Data | same as Sources | custom `.aips-filter-bar` (shared markup — gets the filter-bar fix free) | core `row_actions()` | core native | Core's own stacking, untouched |
| Pending Review | `AIPS_List_Table` → core `WP_List_Table` | custom `.aips-filter-bar` (bespoke wrapper, relocates pagination into its own toolbar) | core `row_actions()` | core `pagination()`, relocated | Core's own stacking, untouched |
| Schedules | `AIPS_List_Table` → core `WP_List_Table` | core `views()`/`search_box()` | core `row_actions()` | core native | Core's own stacking, untouched |
| Authors | same as Schedules | core `views()`/`search_box()` | core `row_actions()` | core native | Core's own stacking, untouched |
| Author Topics | same as Schedules | core `views()`/`search_box()` | core `row_actions()` | core native | Core's own stacking, untouched |
| All other admin pages (~24 more) | not yet audited | — | — | — | Not started — audit before assuming any of the above systems apply as-is |

## 4. Decision log (open questions, not yet resolved)

1. **Kebab menu vs. core hover-only row actions.** Core's `row_actions()` only reveals on hover/focus; touch devices have no hover. Before migrating any of the 6 `AIPS_List_Table` pages, decide whether they should adopt the shared kebab menu (consistent, touch-friendly, but needs a PHP change per subclass to emit the menu markup instead of/alongside `row_actions()`) or keep core's pattern (simpler, but core's own mobile card-stacking CSS is relied on to make it tap-usable — verify that's actually true before deciding it's fine).
2. **`aips-history-filter-*` naming.** Sources and Source Data already copy History's filter-bar inner class names verbatim rather than using a generic name. Renaming to a non-page-prefixed name (`aips-filter-row`, `aips-filter-selects`, etc.) would be clearer but touches 3+ templates and any JS that queries those selectors — worth doing in one pass rather than renaming piecemeal.
3. **`AIPS_List_Table::pagination()` override.** Recommended approach when this is prioritized: pick a new, collision-free class name (not `.aips-pagination` — see item 5 below), then override `pagination()` **once** in the abstract `includes/tables/class-aips-list-table.php` base class to emit it — this cascades to all 6 subclasses from a single change, rather than touching each one. Needs its own PR + test pass given it touches `WP_List_Table` inheritance directly; not attempted in this round.
4. **`.aips-table-responsive` vs. `AIPS_List_Table` pages.** Confirmed these should **not** get `.aips-table-responsive` — WP core already stacks their rows via `data-colname` at 782px, toggled per-column by Screen Options. Applying our card system on top would conflict/double up. If their mobile experience still needs work (e.g. Screen Options isn't an obvious enough escape hatch, or column text truncates), that's a different, separately-scoped problem — don't reach for `.aips-table-responsive`.
5. **Third pagination variant.** Link Report (`admin-link-report.css`) has its own independently-built `.aips-pagination` with no connection to History's `.aips-history-pagination`. Before consolidating, audit whether other pages have likewise grown their own one-off pagination styling — same drift pattern `docs/css-variables-audit.md` already found for color/spacing tokens.

## 5. How to migrate a page (checklist per shipped system)

**Table → cards** (only for a page with a custom `<table class="aips-table">`, never `AIPS_List_Table`):
1. Add `aips-table-responsive` to the `<table>` class list.
2. Add `data-label="<?php esc_attr_e('Column Name', 'ai-post-scheduler'); ?>"` to every `<td>`, matching its `<th>` text exactly (translators keep the same string).
3. Verify no JS does `$row.show()/.hide()` expecting a specific inline `display` value — the card rules use `!important` to win over jQuery's default, but double-check anything unusual.

**Filter bar stacking:**
1. Wrap the filter form in `.aips-filter-bar` (already the generic base class).
2. If reusing the shared inner structure (`.aips-history-filter-row` / `-selects` / `-search` / `-row-dates`), the 782px stacking applies automatically — no CSS changes needed.
3. If using a different inner structure, either adopt the shared one or add a page-specific stacking rule in `admin-responsive.css` (not a new page-specific file).

**Fullscreen modal:**
1. Add `aips-modal-fullscreen-mobile` to the `.aips-modal-content` element.
2. If the modal has a tab nav, add `aips-tabs-scrollable` to it too.

**Row-action kebab menu** (bigger lift — PHP + markup, see decision log item 1 first):
1. Render a `.aips-row-action-overflow-toggle` button + sibling `.aips-row-action-menu` (hidden by default) with `.aips-row-action-item` entries, matching History's row markup (`templates/partials/history-row.php`).
2. No JS wiring needed — `admin.js` already binds the toggle/close-on-outside-click/close-on-item-click behavior globally.
3. Decide whether to keep core's `row_actions()` alongside it or replace it.

**Pagination:** not yet ready for drop-in reuse outside History — see decision log item 3 before migrating a page.
